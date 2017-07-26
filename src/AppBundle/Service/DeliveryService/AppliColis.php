<?php

namespace AppBundle\Service\DeliveryService;

use AppBundle\Entity\Order;
use Predis\Client as Redis;
use GuzzleHttp\Client;

class AppliColis extends Base
{
    private $apiBaseUrl;
    private $apiKey;
    private $client;
    private $logger;

    public function __construct(Redis $redis, $osrmHost, $apiBaseUrl, $apiKey, $logger)
    {
        parent::__construct($redis, $osrmHost);

        $this->apiBaseUrl = $apiBaseUrl;
        $this->apiKey = $apiKey;

        $this->client = new Client([
            'base_uri' => $apiBaseUrl,
            'timeout'  => 10.0,
        ]);

        $this->logger = $logger;
    }

    public function getKey()
    {
        return 'applicolis';
    }

    public function create(Order $order)
    {
        $delivery = $order->getDelivery();

        $deliveryDateTime = $delivery->getDate();
        $collectDateTime = clone $deliveryDateTime;
        $collectDateTime->modify('-30 minutes');

        $json = [
            'collectDateTime' => $collectDateTime->format(\DateTime::ATOM),
            'deliveryDateTime' => $deliveryDateTime->format(\DateTime::ATOM),
            // Customer
            'customerFirstName' => 'Foo',
            'customerLastName' => 'Bar',
            'customerAddress' => $delivery->getDeliveryAddress()->getStreetAddress(),
            'customerLocation' => [
                $delivery->getDeliveryAddress()->getGeo()->getLatitude(),
                $delivery->getDeliveryAddress()->getGeo()->getLongitude(),
            ],
            // Product
            'productDescription' => 'Foodtech delivery',
            'productWidth' => 10,
            'productHeight' => 10,
            'productLenght' => 10,
            'productWeigth' => 1,
            'transports' => [ 'bike' ]
        ];

        $this->logger->info('Sending payload to AppliColis API : ' . json_encode($json));

        $r = $this->client->request('POST', '/external-api/course', [
            'json' => $json,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ]
        ]);

        $body = $r->getBody();

        $this->logger->info('Received response from AppliColis : ' . (string) $body);

        // TODO Store delivery info in database

    }
}
