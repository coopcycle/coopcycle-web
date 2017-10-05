<?php

namespace AppBundle\Service\DeliveryService;

use PHPUnit\Framework\TestCase;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryService;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\DeliveryService\AppliColis;
use AppBundle\Service\RoutingInterface;
use GuzzleHttp\Client;
use Predis\Client as Redis;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;

class AppliColisTest extends TestCase
{
    public function testCreate()
    {
        $routing = $this->prophesize(RoutingInterface::class);
        $client = $this->prophesize(Client::class);
        $logger = $this->prophesize(LoggerInterface::class);

        $data = [
            'id' => 1
        ];

        $response = new \GuzzleHttp\Psr7\Response(200, [], json_encode(['course' => $data]));

        $client
            ->request('POST', '/external-api/course', Argument::type('array'))
            ->willReturn($response);

        $service = new AppliColis($routing->reveal(), $client->reveal(), $logger->reveal());

        $appliColis = new DeliveryService\AppliColis();
        $appliColis->setToken('abc_123456788');

        $restaurant = new Restaurant();
        $restaurant->setDeliveryService($appliColis);

        $order = new Order();
        $order->setRestaurant($restaurant);

        $delivery = new Delivery($order);

        $deliveryAddress = new Address();
        $deliveryAddress->setStreetAddress('84-86 Rue de la Roquette, 75011 Paris');
        $deliveryAddress->setGeo(new GeoCoordinates(48.856203, 2.376259));

        $delivery->setDeliveryAddress($deliveryAddress);
        $delivery->setDate(new \DateTime('+1 day 12:00'));

        $service->create($order);

        $this->assertEquals(['course' => $data], $delivery->getData());
    }
}
