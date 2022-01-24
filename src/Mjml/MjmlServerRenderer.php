<?php

namespace AppBundle\Mjml;

use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MjmlServerRenderer implements RendererInterface
{
    private $mjmlClient;

    public function __construct(HttpClientInterface $mjmlClient)
    {
        $this->mjmlClient = $mjmlClient;
    }

    public function render(string $mjmlContent): string
    {
        $response = $this->mjmlClient->request('POST', '', [
            'body' => $mjmlContent,
            'headers' => [
                'Content-Type' => 'text/plain',
            ]
        ]);

        return (string) $response->getContent();
    }
}
