<?php

namespace AppBundle\Edenred;

use AppBundle\Entity\Base\LocalBusiness;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SynchronizerClient
{
    private $edenredSynchronizerClient;

    public function __construct(
        HttpClientInterface $edenredSynchronizerClient)
    {
        $this->edenredSynchronizerClient = $edenredSynchronizerClient;
    }

    public function getMerchant(LocalBusiness $restaurant)
    {
        return $this->edenredSynchronizerClient->request('GET',
            sprintf('merchants/%s', preg_replace('/\s+/', '', $restaurant->getAdditionalPropertyValue('siret'))));
    }

    public function synchronizeMerchants(array $restaurants)
    {
        $merchants = [];
        foreach ($restaurants as $restaurant) {
            $merchants[] = [
                'siret' => preg_replace('/\s+/', '', $restaurant->getAdditionalPropertyValue('siret')),
                'addInfo' => strval($restaurant->getId()),
            ];
        }

        $data = [
            "merchants" => $merchants
        ];

        return $this->edenredSynchronizerClient->request('POST', 'merchants', [
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ]
        ]);
    }
}
