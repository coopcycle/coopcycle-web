<?php

namespace AppBundle\Partner\LoopEat;

use GuzzleHttp\Client as HttpClient;

class Client
{
    private $client;

    public function __construct(
        HttpClient $client,
        string $username,
        string $password,
        string $clientId,
        string $clientSecret)
    {
        $this->client = $client;
        $this->username = $username;
        $this->password = $password;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function getPrice()
    {
        $response = $this->client->request('POST', '/oauth/token', [
            'auth' => [ $this->clientId, $this->clientSecret ],
            'form_params' => [
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        $response = $this->client->request('GET', '/partners/loopeat/price', [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $data['access_token']),
            ]
        ]);

        return (int) json_decode($response->getBody());
    }
}
