<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingTimeCalculator;
use PHPUnit\Framework\TestCase;

class OrderTimelineCalculatorTest extends TestCase
{
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;

    public function setUp(): void
    {
        $this->preparationTimeCalculator = $this->prophesize(PreparationTimeCalculator::class);
        $this->shippingTimeCalculator = $this->prophesize(ShippingTimeCalculator::class);
    }

    private function createOrder($total, $shippedAt, $state = 'normal')
    {
        $restaurant = new Restaurant();
        $restaurant->setState($state);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
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
                '15 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:05:00'),
            ],
            [
                $this->createOrder(3000, '2018-08-25 13:30:00'),
                '15 minutes',
                '15 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:00:00'),
            ],
            [
                $this->createOrder(6000, '2018-08-25 13:30:00'),
                '30 minutes',
                '15 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:45:00'),
            ],
            // state = rush
            [
                $this->createOrder(1500, '2018-08-25 13:30:00', 'rush'),
                '20 minutes',
                '15 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:55:00'),
            ],
            [
                $this->createOrder(3000, '2018-08-25 13:30:00', 'rush'),
                '30 minutes',
                '15 minutes',
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:45:00'),
            ],
            [
                $this->createOrder(6000, '2018-08-25 13:30:00', 'rush'),
                '45 minutes',
                '15 minutes',
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
        $shippingTime,
        \DateTime $dropoffExpectedAt,
        \DateTime $pickupExpectedAt,
        \DateTime $preparationExpectedAt)
    {
        $this->preparationTimeCalculator
            ->calculate($order)
            ->willReturn($preparationTime);

        $this->preparationTimeCalculator
            ->createForRestaurant($order->getRestaurant())
            ->willReturn($this->preparationTimeCalculator->reveal());

        $this->shippingTimeCalculator
            ->calculate($order)
            ->willReturn($shippingTime);

        $this->calculator = new OrderTimelineCalculator(
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal()
        );

        $timeline = $this->calculator->calculate($order);

        $this->assertEquals($dropoffExpectedAt, $timeline->getDropoffExpectedAt());
        $this->assertEquals($pickupExpectedAt, $timeline->getPickupExpectedAt());
        $this->assertEquals($preparationExpectedAt, $timeline->getPreparationExpectedAt());
    }
}
