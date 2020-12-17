<?php

namespace Utopia\Abuse\Adapters;

use Utopia\Abuse\Adapter;

class ReCaptcha implements Adapter
{
    /**
     * Use this for communication between your site and Google.
     * Be sure to keep it a secret.
     *
     * @var string
     */
    protected $secret = '';

    /**
     * The value of 'g-recaptcha-response'.
     *
     * @var string
     */
    protected $response = '';

    /**
     * The end user's ip address
     *
     * @var string
     */
    protected $remoteIP = '';

    /**
     * ReCaptcha Adapter
     *
     * See more information about the implementation instructions
     * @see https://developers.google.com/recaptcha/docs/verify
     *
     * Admin Panel
     * @see https://www.google.com/recaptcha/admin
     *
     * @param string $secret
     * @param string $response
     * @param string $remoteIP
     */
    public function __construct($secret, $response, $remoteIP)
    {
        $this->secret   = $secret;
        $this->response = $response;
        $this->remoteIP = $remoteIP;
    }

    /**
     * Check
     *
     * Check if user is human or not
     */
    public function check()
    {
        $url    = 'https://www.google.com/recaptcha/api/siteverify';
        $fields = array(
            'secret'    => \urlencode($this->secret),
            'response'  => \urlencode($this->response),
            'remoteip'  => \urlencode($this->remoteIP),
        );

        //open connection
        $ch = \curl_init();

        //set the url, number of POST vars, POST data
        \curl_setopt($ch,CURLOPT_URL, $url);
        \curl_setopt($ch,CURLOPT_POST, \count($fields));
        \curl_setopt($ch,CURLOPT_POSTFIELDS, \http_build_query($fields));
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //execute post
        $result = \json_decode((string)\curl_exec($ch), true);

        //close connection
        \curl_close($ch);

        return $result['success'];
    }

    /**
     * Delete logs older than $seconds seconds
     * 
     * @param int $seconds 
     * 
     * @return bool   
     */
    public function deleteLogsOlderThan(int $seconds):bool
    {
        return true;
    }

    /**
     * Get all logs
     *
     * Returns all the logs that are currently in the DB
     *
     * @return array
     */
    public function getAllLogs(): array 
    {  
       return array(); 
    }
}