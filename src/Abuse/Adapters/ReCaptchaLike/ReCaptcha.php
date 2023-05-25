<?php

declare(strict_types=1);

namespace Utopia\Abuse\Adapters\ReCaptchaLike;

class ReCaptcha extends ReCaptchaLike
{
    /**
     * @inheritDoc
     */
    protected function decideByResult(array $result): bool
    {
        // reCAPTCHA v3 returns a score (1.0 is very likely a good interaction, 0.0 is very likely a bot) @see https://developers.google.com/recaptcha/docs/v3#interpreting_the_score
        return $result['success'] && $result['score'] > $this->threshold;
    }

    /**
     * @inheritDoc
     */
    protected function getSiteVerifyUrl(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }
}
