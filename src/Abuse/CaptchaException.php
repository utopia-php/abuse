<?php

namespace Utopia\Abuse;

/**
 * Thrown by the Captcha facade when the per-token attempt cap is exhausted.
 * Callers should map this to an HTTP 429 ("reload for a new challenge").
 */
class CaptchaException extends \Exception
{
}
