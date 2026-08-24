<?php

namespace AppBundle\Cyke;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Client
{
    public function __construct(
        private HttpClientInterface $cykeClient)
    {}

    /**
     * @return array
     */
    public function getPackageTypes(string $email, string $token)
    {
        $response = $this->cykeClient->request('GET', 'package_types', [
            'headers' => [
                'X-User-Email' => $email,
                'X-User-Token' => $token,
            ],
        ]);

        return $response->toArray();
    }
}
