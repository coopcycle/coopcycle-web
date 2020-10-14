<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\PreparationTimeResolver;
use AppBundle\Utils\PickupTimeResolver;
use PHPUnit\Framework\TestCase;
use Redis;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class PreparationTimeResolverTest extends TestCase
{
    use ProphecyTrait;

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
                $takeaway = false,
                $pickup   = new \DateTime('2020-04-10 12:15:00'),
                '20 minutes',
                $expected = new \DateTime('2020-04-10 11:55:00'),
                $dropoff  = new \DateTime('2020-04-10 12:30:00'),
                $delay    = null,
            ],
            [
                $takeaway = false,
                $pickup   = new \DateTime('2020-04-10 12:15:00'),
                '20 minutes',
                $expected = new \DateTime('2020-04-10 11:55:00'),
                $dropoff  = new \DateTime('2020-04-10 12:30:00'),
                $delay    = '0',
            ],
            [
                $takeaway = false,
                $pickup   = new \DateTime('2020-04-10 12:15:00'),
                '20 minutes',
                $expected = new \DateTime('2020-04-10 11:45:00'),
                $dropoff  = new \DateTime('2020-04-10 12:30:00'),
                $delay    = '10',
            ],
            [
                $takeaway = true,
                $pickup   = new \DateTime('2020-04-10 12:15:00'),
                '30 minutes',
                $expected = new \DateTime('2020-04-10 11:45:00'),
                $dropoff  = new \DateTime('2020-04-10 12:15:00'),
                $delay    = null,
            ],
        ];
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolve(
        bool $takeaway,
        \DateTime $pickup,
        $preparationTime,
        \DateTime $expected,
        \DateTime $dropoff = null,
        $preparationDelay = null)
    {
        $this->redis
            ->get('foodtech:preparation_delay')
            ->willReturn($preparationDelay);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($this->restaurant->reveal());
        $order
            ->isTakeaway()
            ->willReturn($takeaway);

        $this->preparationTimeCalculator
            ->calculate($order->reveal())
            ->willReturn($preparationTime);

        $this->pickupTimeResolver
            ->resolve($order->reveal(), $dropoff)
            ->willReturn($pickup);

        $this->assertEquals($expected, $this->resolver->resolve($order->reveal(), $dropoff));
    }
}
