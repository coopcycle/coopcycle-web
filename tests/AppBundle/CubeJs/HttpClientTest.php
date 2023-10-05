<?php

namespace Tests\AppBundle\CubeJs;

use AppBundle\CubeJs\HttpClient as CubeJsHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HttpClientTest extends TestCase
{
	public function testContinueWait()
    {
    	$responses = [
		    new MockResponse('{"error":"Continue wait"}', ['http_code' => 200]),
		    new MockResponse('{"error":"Continue wait"}', ['http_code' => 200]),
		   	new MockResponse('{"data":[]}', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
		];

		$client = new CubeJsHttpClient(new MockHttpClient($responses));

		$response = $client->request('POST', 'load', [
			'body' => json_encode([])
		]);

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('{"data":[]}', $response->getContent());
    }
}
