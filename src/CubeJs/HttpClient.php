<?php

namespace AppBundle\CubeJs;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;

class HttpClient extends RetryableHttpClient
{
    public function __construct(HttpClientInterface $client)
    {
        $retryStrategy = new ContinueWaitRetryStrategy();

        parent::__construct($client, $retryStrategy);
    }
}
