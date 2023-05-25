<?php

namespace Utopia\Abuse\Adapters\ReCaptchaLike;

use Exception;
use Utopia\Abuse\Adapter;

abstract class ReCaptchaLike implements Adapter
{
    /**
     * Use this for communication between your site and Google.
     * Be sure to keep it a secret.
     *
     * @var string
     */
    protected string $secret = '';

    /**
     * The value of 'g-recaptcha-response'.
     *
     * @var string
     */
    protected string $response = '';

    /**
     * The end user's ip address
     *
     * @var string
     */
    protected string $remoteIP = '';

    /**
     * Threshold that is used to determine threats
     */
    protected float $threshold;

    /**
     * ReCaptcha Adapter
     *
     * See more information about the implementation instructions
     *
     * @see https://developers.google.com/recaptcha/docs/verify
     *
     * Admin Panel
     * @see https://www.google.com/recaptcha/admin
     *
     * @param  string  $secret
     * @param  string  $response
     * @param  string  $remoteIP
     * @param  float   $threshold By default, you can use a threshold of 0.5. @see https://developers.google.com/recaptcha/docs/v3#interpreting_the_score
     */
    public function __construct(string $secret, string $response, string $remoteIP, float $threshold = 0.5)
    {
        $this->secret = $secret;
        $this->response = $response;
        $this->remoteIP = $remoteIP;
        $this->threshold = $threshold;
    }

    /**
     * @inheritDoc
     */
    public function check(float $score = 0.5): bool
    {
        $this->threshold = $score;

        return $this->isSafe() === false;
    }

    /**
     * @inheritDoc
     */
    public function isSafe(): bool
    {
        $fields = [
            'secret' => \urlencode($this->secret),
            'response' => \urlencode($this->response),
            'remoteip' => \urlencode($this->remoteIP),
        ];

        //open connection
        $ch = \curl_init();

        //set the url, number of POST vars, POST data
        \curl_setopt($ch, CURLOPT_URL, $this->getSiteVerifyUrl());
        \curl_setopt($ch, CURLOPT_POST, \count($fields));
        \curl_setopt($ch, CURLOPT_POSTFIELDS, \http_build_query($fields));
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //execute post
        /** @var array<string, mixed> $result */
        try {
            $result = \json_decode((string)\curl_exec($ch), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $_) {
            return false;
        } finally {
            //close connection
            \curl_close($ch);
        }

        return $this->decideByResult($result);
    }

    /**
     * Implementation how score is interpreted.
     *
     * @param array $result Returned by reCAPTCHA service
     * @return bool True if is safe
     */
    abstract protected function decideByResult(array $result): bool;

    /**
     * Implementation how to get site-verify url.
     *
     * @return string Url of the site-verify endpoint.
     */
    abstract protected function getSiteVerifyUrl(): string;

    /**
     * Delete logs older than $datetime
     *
     * @param  string  $datetime
     * @return bool
     *
     * @throws Exception
     */
    public function cleanup(string $datetime): bool
    {
        throw new Exception('Method not supported');
    }

    /**
     * Get abuse logs
     *
     * Return logs with an offset and limit
     *
     * @param  int|null  $offset
     * @param  int|null  $limit
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getLogs(?int $offset = null, ?int $limit = 25): array
    {
        throw new Exception('Method not supported');
    }
}
