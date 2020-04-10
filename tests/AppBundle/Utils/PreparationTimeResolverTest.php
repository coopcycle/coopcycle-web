<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\PreparationTimeResolver;
use AppBundle\Utils\PickupTimeResolver;
use PHPUnit\Framework\TestCase;
use Predis\Client as Redis;
use Prophecy\Argument;

class PreparationTimeResolverTest extends TestCase
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
        $this->pickupTimeResolver = $this->prophesize(PickupTimeResolver::class);

        $this->resolver = new PreparationTimeResolver(
            $this->preparationTimeCalculator->reveal(),
            $this->pickupTimeResolver->reveal(),
            $this->redis->reveal()
        );
    }

    public function resolveProvider()
    {
        return [
            [
                $dropoff  = new \DateTime('2020-04-10 12:30:00'),
                $pickup   = new \DateTime('2020-04-10 12:15:00'),
                '20 minutes',
                $expected = new \DateTime('2020-04-10 11:55:00'),
                null
            ],
            [
                $dropoff  = new \DateTime('2020-04-10 12:30:00'),
                $pickup   = new \DateTime('2020-04-10 12:15:00'),
                '20 minutes',
                $expected = new \DateTime('2020-04-10 11:55:00'),
                '0'
            ],
            [
                $dropoff  = new \DateTime('2020-04-10 12:30:00'),
                $pickup   = new \DateTime('2020-04-10 12:15:00'),
                '20 minutes',
                $expected = new \DateTime('2020-04-10 11:45:00'),
                '10'
            ],
        ];
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolve(
        \DateTime $dropoff,
        \DateTime $pickup,
        $preparationTime,
        \DateTime $expected,
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
            ->calculate($order->reveal())
            ->willReturn($preparationTime);

        $this->preparationTimeCalculator
            ->createForRestaurant($this->restaurant->reveal())
            ->willReturn($this->preparationTimeCalculator->reveal());

        $this->pickupTimeResolver
            ->resolve($order->reveal(), $dropoff)
            ->willReturn($pickup);

        $this->assertEquals($expected, $this->resolver->resolve($order->reveal(), $dropoff));
    }
}
