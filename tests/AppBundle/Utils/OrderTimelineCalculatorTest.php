<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\PreparationTimeCalculator;
use PHPUnit\Framework\TestCase;

class OrderTimelineCalculatorTest extends TestCase
{
    private $preparationTimeCalculator;

    public function setUp()
    {
        $this->preparationTimeCalculator = $this->prophesize(PreparationTimeCalculator::class);
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
            ->getItemsTotal()
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
                '10 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:05:00'),
            ],
            [
                $this->createOrder(3000, '2018-08-25 13:30:00'),
                '15 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:00:00'),
            ],
            [
                $this->createOrder(6000, '2018-08-25 13:30:00'),
                '30 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:45:00'),
            ],
            // state = rush
            [
                $this->createOrder(1500, '2018-08-25 13:30:00', 'rush'),
                '20 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:55:00'),
            ],
            [
                $this->createOrder(3000, '2018-08-25 13:30:00', 'rush'),
                '30 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:45:00'),
            ],
            [
                $this->createOrder(6000, '2018-08-25 13:30:00', 'rush'),
                '45 minutes',
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
        $preparationTime,
        \DateTime $dropoffExpectedAt,
        \DateTime $pickupExpectedAt,
        \DateTime $preparationExpectedAt)
    {
        $this->routing = $this->prophesize(RoutingInterface::class);

        $this->routing
            ->getDuration(
                $order->getRestaurant()->getAddress()->getGeo(),
                $order->getShippingAddress()->getGeo()
            )
            ->willReturn(15 * 60); // 15 minutes

        $this->preparationTimeCalculator
            ->calculate($order)
            ->willReturn($preparationTime);

        $this->calculator = new OrderTimelineCalculator(
            $this->routing->reveal(),
            $this->preparationTimeCalculator->reveal()
        );

        $timeline = $this->calculator->calculate($order);

        $this->assertEquals($dropoffExpectedAt, $timeline->getDropoffExpectedAt());
        $this->assertEquals($pickupExpectedAt, $timeline->getPickupExpectedAt());
        $this->assertEquals($preparationExpectedAt, $timeline->getPreparationExpectedAt());
    }
}
