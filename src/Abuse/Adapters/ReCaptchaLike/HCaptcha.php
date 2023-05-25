<?php

declare(strict_types=1);

namespace Utopia\Abuse\Adapters\ReCaptchaLike;

class HCaptcha extends ReCaptchaLike
{
    /**
     * @inheritDoc
     */
    protected function decideByResult(array $result): bool
    {
        // hCaptcha Enterprise scores are risk scores, and thus they run from 0.0 (no risk) to 1.0 (confirmed threat).
        return $result['success'] && $result['score'] < $this->threshold;
    }

    /**
     * @inheritDoc
     */
    protected function getSiteVerifyUrl(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }
}
