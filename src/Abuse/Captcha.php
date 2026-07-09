<?php

namespace Utopia\Abuse;

use Utopia\WAF\Challenge\Clearance;
use Utopia\WAF\Challenge\Context;
use Utopia\WAF\Challenge\Interactive;
use Utopia\WAF\Challenge\Signer;

/**
 * Captcha — the anti-abuse facade over the first-party interactive humanity gate
 * (premtsd-code/captcha, namespace Utopia\WAF\Challenge\*).
 *
 * This is the single anti-abuse entry point the WAF calls for the *interactive*
 * (slider) challenge. The leaf package owns the humanity primitives — the
 * interactive slider (Interactive), stateless HMAC clearance (Clearance), and the
 * signed-token core (Signer). This package owns rate-limiting (the TimeLimit
 * adapters). The facade is where the two meet: it issues and verifies the slider
 * challenge, mints and validates clearance, and folds a per-token attempt cap into
 * the interactive verify so a bot cannot spray buckets against one rendered image.
 *
 * The silent (invisible) tier lives in utopia-php/waf, next to the scoring engine
 * that evaluates it, and is not part of this facade.
 *
 * The gate primitives are stateless, so the stateful attempt cap is enforced here,
 * keyed on the challenge token's own nonce (via Interactive::reference()), with the
 * same lifetime as the token.
 */
final class Captcha
{
    private readonly Interactive $interactive;

    private readonly Clearance $clearance;

    /**
     * @param Signer                        $signer         HMAC signer holding the kid keyset; signs and
     *                                                      verifies every challenge and clearance token
     * @param TimeLimitAdapterProvider|null $attemptLimiter a factory returning a fresh TimeLimit for a
     *                                                      given key. When present, the interactive verify
     *                                                      is capped at Interactive::MAX_ATTEMPTS guesses
     *                                                      per token; when null, verify is correctness-only
     *                                                      (fail-open on the cap).
     */
    public function __construct(
        private readonly Signer $signer,
        private readonly ?TimeLimitAdapterProvider $attemptLimiter = null,
    ) {
        $this->interactive = new Interactive($signer);
        $this->clearance = new Clearance($signer);
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
     * Issue an interactive slider challenge for the given target gap.
     *
     * @return array{token: string, gap: int, expiresAt: int, expiresIn: int}
     */
    public function issueInteractive(
        Context $context,
        int $gap,
        ?int $clearanceTtl = null,
    ): array {
        return $this->interactive->issue($context, $gap, $clearanceTtl);
    }

    /**
     * Verify an interactive slider solution, enforcing the per-token attempt cap.
     *
     * The cap bounds guesses to Interactive::MAX_ATTEMPTS per rendered token so a
     * bot cannot spray the (coarse) buckets against one image; a miss then costs a
     * fresh (render-expensive, rate-limited) challenge. The counter is keyed on the
     * token's own nonce and expires with it. A forged/garbage token cannot seed or
     * exhaust a real token's counter (reference() returns null for it) — verify()
     * still decides correctness.
     *
     * @throws CaptchaException when the attempt cap is exhausted (map to HTTP 429)
     */
    public function verifyInteractive(
        string $token,
        int $offset,
        Context $context,
    ): bool {
        $this->guardAttempts($token);

        return $this->interactive->verify($token, $offset, $context);
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
     * Direct access to the underlying interactive primitive, for callers that need
     * capabilities beyond the facade (e.g. the interstitial renderer, or reading a
     * token's clearance TTL after a verified solve).
     */
    public function interactive(): Interactive
    {
        return $this->interactive;
    }

    /**
     * Enforce the attempt cap for an interactive token. No-op (fail-open) when no
     * limiter is wired or the token is not an authentic interactive challenge.
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
