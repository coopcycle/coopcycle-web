<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingTimeCalculator;
use AppBundle\Utils\PickupTimeCalculator;
use PHPUnit\Framework\TestCase;
use Predis\Client as Redis;
use Prophecy\Argument;

class PickupTimeCalculatorTest extends TestCase
{
    private $restaurant;
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;

    private $shippingDateFilter;

    public function setUp(): void
    {
        $this->restaurant = $this->prophesize(Restaurant::class);

        $this->redis = $this->prophesize(Redis::class);
        $this->preparationTimeCalculator = $this->prophesize(PreparationTimeCalculator::class);
        $this->shippingTimeCalculator = $this->prophesize(ShippingTimeCalculator::class);

        $this->calculator = new PickupTimeCalculator(
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal(),
            $this->redis->reveal()
        );
    }

    public function calculateProvider()
    {
        return [
            [
                new \DateTime('2020-04-10 12:30:00'),
                '15 minutes',
                '10 minutes',
                new \DateTime('2020-04-10 12:05:00'),
                null
            ],
            [
                new \DateTime('2020-04-10 12:30:00'),
                '15 minutes',
                '10 minutes',
                new \DateTime('2020-04-10 12:05:00'),
                '0'
            ],
            [
                new \DateTime('2020-04-10 12:30:00'),
                '15 minutes',
                '10 minutes',
                new \DateTime('2020-04-10 11:55:00'),
                '10'
            ],
        ];
    }

    /**
     * @dataProvider calculateProvider
     */
    public function testCalculate(
        \DateTime $dropoff,
        $preparationTime,
        $shippingTime,
        \DateTime $pickup,
        $preparationDelay = null)
    {
        $this->redis
            ->get('foodtech:preparation_delay')
            ->willReturn($preparationDelay);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($this->restaurant->reveal());

        $this->preparationTimeCalculator
            ->calculate(Argument::type(OrderInterface::class))
            ->willReturn($preparationTime);

        $this->preparationTimeCalculator
            ->createForRestaurant(Argument::type(Restaurant::class))
            ->willReturn($this->preparationTimeCalculator->reveal());

        $this->shippingTimeCalculator
            ->calculate(Argument::type(OrderInterface::class))
            ->willReturn($shippingTime);

        $this->assertEquals($pickup, $this->calculator->calculate($order->reveal(), $dropoff));
    }
}
