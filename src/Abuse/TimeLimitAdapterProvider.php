<?php

namespace Utopia\Abuse;

/**
 * Factory for per-token attempt-cap limiters.
 *
 * The Captcha facade is backend-agnostic: it does not know whether the counter
 * lives in Redis, a database, or memory. A caller supplies a provider that,
 * given a challenge token's reference (its nonce), returns a fresh Adapter
 * (typically a TimeLimit keyed on that reference with Interactive::MAX_ATTEMPTS
 * over Interactive::NONCE_TTL). This mirrors the `$timelimit('key', limit, ttl)`
 * factory pattern the WAF already uses at its integration layer.
 */
interface TimeLimitAdapterProvider
{
    /**
     * Return a ready-to-check limiter scoped to the given per-token reference.
     */
    public function forReference(string $reference): Adapter;
}
