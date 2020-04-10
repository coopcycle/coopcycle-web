<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\ShippingTimeCalculator;
use AppBundle\Utils\PickupTimeResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class PickupTimeResolverTest extends TestCase
{
    private $shippingTimeCalculator;
    private $resolver;

    public function setUp(): void
    {
        $this->shippingTimeCalculator = $this->prophesize(ShippingTimeCalculator::class);

        $this->resolver = new PickupTimeResolver(
            $this->shippingTimeCalculator->reveal()
        );
    }

    public function resolveProvider()
    {
        return [
            [
                $dropoff = new \DateTime('2020-04-10 12:30:00'),
                '15 minutes',
                $pickup  = new \DateTime('2020-04-10 12:15:00'),
            ],
            [
                $dropoff = new \DateTime('2020-04-10 12:30:00'),
                '7 minutes',
                $pickup  = new \DateTime('2020-04-10 12:23:00'),
            ],
            [
                $dropoff = new \DateTime('2020-04-10 12:30:00'),
                '32 minutes',
                $pickup  = new \DateTime('2020-04-10 11:58:00'),
            ],
        ];
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolve(
        \DateTime $dropoff,
        $shippingTime,
        \DateTime $pickup)
    {
        $order = $this->prophesize(OrderInterface::class);

        $this->shippingTimeCalculator
            ->calculate($order->reveal())
            ->willReturn($shippingTime);

        $this->assertEquals($pickup, $this->resolver->resolve($order->reveal(), $dropoff));
    }
}
