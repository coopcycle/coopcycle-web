<?php

namespace AppBundle\Payment;

use AppBundle\Service\SettingsManager;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;

class PawapayClientFactory
{
    public function __construct(private SettingsManager $settingsManager)
    {
    }

    public function __invoke(string $baseUri): HttpClientInterface
    {
        $options = [
            'base_uri' => $baseUri
        ];

        $apiKey = $this->settingsManager->get('pawapay_api_key');
        if (!empty($apiKey)) {
            $options['headers'] = [
                'Authorization' => sprintf('Bearer %s', $apiKey)
            ];
        }

        return HttpClient::create($options);
    }
}
