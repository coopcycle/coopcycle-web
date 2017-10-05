<?php

namespace AppBundle\Service\DeliveryService;

use AppBundle\Entity\Order;
use AppBundle\Service\RoutingInterface;
use Predis\Client as Redis;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7;
use Psr\Log\LoggerInterface;

class AppliColis extends Base
{
    private $client;
    private $logger;

    public function __construct(RoutingInterface $routing, Client $client, LoggerInterface $logger)
    {
        parent::__construct($routing);

        $this->client = $client;
        $this->logger = $logger;
    }

    public function getKey()
    {
        return 'applicolis';
    }

    private function getApiToken(Order $order)
    {
        return $order->getRestaurant()->getDeliveryService()->getToken();
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

        try {

           $r = $this->client->request('POST', '/external-api/course', [
                'json' => $json,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getApiToken($order),
                    'Content-Type' => 'application/json'
                ]
            ]);

            $body = $r->getBody();

            $this->logger->info('Received response from AppliColis : ' . (string) $body);

            $data = json_decode((string) $body, true);
            $delivery->setData($data);

        } catch (ClientException $e) {
            $this->logger->error(Psr7\str($e->getResponse()));
        } catch (ServerException $e) {
            $this->logger->error(Psr7\str($e->getResponse()));
        }
    }
}
