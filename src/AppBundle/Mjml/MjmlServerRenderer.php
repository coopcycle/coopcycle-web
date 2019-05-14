<?php

namespace AppBundle\Mjml;

use GuzzleHttp\Client as HttpClient;
use NotFloran\MjmlBundle\Renderer\RendererInterface;

class MjmlServerRenderer implements RendererInterface
{
    private $client;

    public function __construct(HttpClient $client)
    {
        $this->client = $client;
    }

    public function render(string $mjmlContent): string
    {
    	$response = $this->client->post('/', [
		    'body' => $mjmlContent,
		    'headers' => [
		        'Content-Type' => 'text/plain',
		    ]
		]);

		return (string) $response->getBody();
    }
}
