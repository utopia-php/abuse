<?php

declare(strict_types=1);

namespace Utopia\Abuse\Adapters\ReCaptchaLike;

class CfTurnstile extends ReCaptchaLike
{
    /**
     * @inheritDoc
     */
    protected function decideByResult(array $result): bool
    {
        // https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
        return $result['success'];
    }

    /**
     * @inheritDoc
     */
    protected function getSiteVerifyUrl(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }
}
