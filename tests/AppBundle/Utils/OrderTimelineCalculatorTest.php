<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimelineCalculator;
use PHPUnit\Framework\TestCase;

class OrderTimelineCalculatorTest extends TestCase
{
    private $config;

    public function setUp()
    {
        $this->config = [
            'restaurant.state == "rush" and order.total < 2000'        => '20 minutes',
            'restaurant.state == "rush" and order.total in 2000..5000' => '30 minutes',
            'restaurant.state == "rush" and order.total > 5000'        => '45 minutes',
            'order.total <= 2000'                                      => '10 minutes',
            'order.total in 2000..5000'                                => '15 minutes',
            'order.total > 5000'                                       => '30 minutes',
        ];
    }

    private function createOrder($total, $shippedAt, $state = 'normal')
    {
        $restaurantAddressCoords = new GeoCoordinates();
        $restaurantAddress = new Address();
        $restaurantAddress->setGeo($restaurantAddressCoords);

        $shippingAddressCoords = new GeoCoordinates();
        $shippingAddress = new Address();
        $shippingAddress->setGeo($shippingAddressCoords);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);
        $restaurant->setState($state);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);
        $order
            ->getTotal()
            ->willReturn($total);
        $order
            ->getShippedAt()
            ->willReturn(new \DateTime($shippedAt));

        return $order->reveal();
    }

    public function calculateProvider()
    {
        return [
            // state = normal
            [
                $this->createOrder(1500, '2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:05:00'),
            ],
            [
                $this->createOrder(3000, '2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:00:00'),
            ],
            [
                $this->createOrder(6000, '2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:45:00'),
            ],
            // state = rush
            [
                $this->createOrder(1500, '2018-08-25 13:30:00', 'rush'),
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:55:00'),
            ],
            [
                $this->createOrder(3000, '2018-08-25 13:30:00', 'rush'),
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:45:00'),
            ],
            [
                $this->createOrder(6000, '2018-08-25 13:30:00', 'rush'),
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:30:00'),
            ],
        ];
    }

    /**
     * @dataProvider calculateProvider
     */
    public function testCalculate(
        OrderInterface $order,
        \DateTime $dropoffExpectedAt,
        \DateTime $pickupExpectedAt,
        \DateTime $preparationExpectedAt)
    {
        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->calculator = new OrderTimelineCalculator($this->routing->reveal(), $this->config);

        $this->routing
            ->getDuration(
                $order->getRestaurant()->getAddress()->getGeo(),
                $order->getShippingAddress()->getGeo()
            )
            ->willReturn(15 * 60); // 15 minutes

        $timeline = $this->calculator->calculate($order);

        $this->assertEquals($dropoffExpectedAt, $timeline->getDropoffExpectedAt());
        $this->assertEquals($pickupExpectedAt, $timeline->getPickupExpectedAt());
        $this->assertEquals($preparationExpectedAt, $timeline->getPreparationExpectedAt());
    }
}
