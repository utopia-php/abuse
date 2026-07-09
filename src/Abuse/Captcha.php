<?php

namespace Utopia\Abuse;

use Utopia\WAF\Challenge\Clearance;
use Utopia\WAF\Challenge\Context;
use Utopia\WAF\Challenge\Interactive;
use Utopia\WAF\Challenge\Issuer;
use Utopia\WAF\Challenge\Signer;
use Utopia\WAF\Challenge\Verifier;

/**
 * Captcha — the anti-abuse facade over the first-party humanity gate
 * (premtsd-code/captcha, namespace Utopia\WAF\Challenge\*).
 *
 * This is the single anti-abuse entry point the WAF calls. The leaf package
 * owns the humanity primitives — stateless HMAC challenge tokens (Signer,
 * Issuer, Verifier, Clearance), and the interactive slider (Interactive) with
 * offset-bound proof-of-work. This package owns rate-limiting (the TimeLimit
 * adapters). The facade is where the two meet: it issues and verifies silent
 * and interactive challenges, mints and validates clearance, and folds a
 * per-token attempt cap into interactive verify so a bot cannot spray buckets
 * against one rendered image.
 *
 * The gate primitives are stateless, so the stateful attempt cap is enforced
 * here, keyed on the challenge token's own nonce (via Interactive::reference()),
 * with the same lifetime as the token.
 */
final class Captcha
{
    private readonly Issuer $issuer;

    private readonly Verifier $verifier;

    private readonly Clearance $clearance;

    private readonly Interactive $interactive;

    /**
     * @param Signer       $signer         HMAC signer holding the kid keyset; signs and
     *                                      verifies every challenge and clearance token
     * @param TimeLimitAdapterProvider|null $attemptLimiter a factory returning a fresh
     *                                      TimeLimit for a given key. When present, the
     *                                      interactive verify is capped at MAX_ATTEMPTS
     *                                      guesses per token; when null, verify is
     *                                      correctness-only (fail-open on the cap).
     */
    public function __construct(
        private readonly Signer $signer,
        private readonly ?TimeLimitAdapterProvider $attemptLimiter = null,
    ) {
        $this->issuer = new Issuer($signer);
        $this->verifier = new Verifier($signer);
        $this->clearance = new Clearance($signer);
        $this->interactive = new Interactive($signer);
    }

    /**
     * Bind a request context: project + audience + client IP. Only the IP's
     * network prefix is bound (see Utopia\WAF\Challenge\Ip::prefix()).
     */
    public function context(string $projectId, string $audience, string $ip): Context
    {
        return new Context($projectId, $audience, $ip);
    }

    /**
     * Issue a silent (invisible) proof-of-work challenge.
     *
     * @return array<string, mixed> the challenge payload the client solves
     */
    public function issueSilent(
        Context $context,
        int $difficulty = Issuer::DIFFICULTY_DEFAULT,
        ?int $clearanceTtl = null,
        int $memory = 0,
    ): array {
        return $this->issuer->issue($context, $difficulty, $clearanceTtl, $memory);
    }

    /**
     * Verify a silent proof-of-work solution.
     */
    public function verifySilent(string $nonce, string $solution, Context $context): bool
    {
        return $this->verifier->verify($nonce, $solution, $context);
    }

    /**
     * Issue an interactive slider challenge for the given target gap.
     *
     * @return array<string, mixed>
     */
    public function issueInteractive(
        Context $context,
        int $gap,
        int $difficulty = Issuer::DIFFICULTY_DEFAULT,
        int $memory = 0,
        ?int $clearanceTtl = null,
    ): array {
        return $this->interactive->issue($context, $gap, $difficulty, $memory, $clearanceTtl);
    }

    /**
     * Verify an interactive slider solution, enforcing the per-token attempt cap.
     *
     * The cap bounds guesses to Interactive::MAX_ATTEMPTS per rendered token so a
     * bot cannot spray the (coarse) buckets against one image; the offset-bound
     * PoW makes each of those guesses cost a real solve. The counter is keyed on
     * the token's own nonce and expires with it. A forged/garbage token cannot
     * seed or exhaust a real token's counter (reference() returns null for it) —
     * verify() still decides correctness.
     *
     * @throws CaptchaException when the attempt cap is exhausted (map to HTTP 429)
     */
    public function verifyInteractive(
        string $token,
        int $offset,
        string $powSolution,
        Context $context,
    ): bool {
        $this->guardAttempts($token);

        return $this->interactive->verify($token, $offset, $powSolution, $context);
    }

    /**
     * Mint a stateless clearance token once a challenge has been passed.
     */
    public function issueClearance(Context $context, int $ttl = Clearance::TTL_DEFAULT): string
    {
        return $this->clearance->issue($context, $ttl);
    }

    /**
     * Validate a clearance token against the current request context.
     */
    public function verifyClearance(string $token, Context $context): bool
    {
        return $this->clearance->verify($token, $context);
    }

    /**
     * The remaining clearance TTL a solved silent challenge would grant, in
     * seconds, or null if the nonce is not an authentic silent challenge.
     */
    public function silentClearanceTtl(string $nonce): ?int
    {
        return $this->issuer->clearanceTtl($nonce);
    }

    /**
     * Direct access to the underlying gate primitives, for callers that need
     * capabilities beyond the facade (e.g. the interstitial renderer).
     */
    public function interactive(): Interactive
    {
        return $this->interactive;
    }

    /**
     * Enforce the attempt cap for an interactive token. No-op (fail-open) when
     * no limiter is wired or the token is not an authentic interactive challenge.
     *
     * @throws CaptchaException when the cap for this token's nonce is exhausted
     */
    private function guardAttempts(string $token): void
    {
        if ($this->attemptLimiter === null) {
            return;
        }

        $reference = $this->interactive->reference($token);
        if ($reference === null) {
            return;
        }

        $limiter = $this->attemptLimiter->forReference($reference);
        if ((new Abuse($limiter))->check()) {
            throw new CaptchaException('Too many attempts for this challenge. Reload for a new one.');
        }
    }
}
