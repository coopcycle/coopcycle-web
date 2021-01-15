<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\ShippingTimeCalculator;
use AppBundle\Utils\PickupTimeResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class PickupTimeResolverTest extends TestCase
{
    use ProphecyTrait;

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
                '15 minutes',
                $pickup  = new \DateTime('2020-04-10 12:15:00'),
                $takeaway = false,
                $dropoff = new \DateTime('2020-04-10 12:30:00'),
            ],
            [
                '7 minutes',
                $pickup  = new \DateTime('2020-04-10 12:23:00'),
                $takeaway = false,
                $dropoff = new \DateTime('2020-04-10 12:30:00'),
            ],
            [
                '32 minutes',
                $pickup  = new \DateTime('2020-04-10 11:58:00'),
                $takeaway = false,
                $dropoff = new \DateTime('2020-04-10 12:30:00'),
            ],
            [
                '0 minutes',
                $pickup  = new \DateTime('2020-04-10 12:00:00'),
                $takeaway = true,
                $dropoff = new \DateTime('2020-04-10 12:00:00'),
            ],
        ];
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolve(
        $shippingTime,
        \DateTime $pickup,
        bool $takeaway,
        \DateTime $dropoff = null)
    {
        $order = $this->prophesize(OrderInterface::class);
        $order
            ->isTakeaway()
            ->willReturn($takeaway);

        if (!$takeaway) {
            $order
                ->getShippingTimeRange()
                ->willReturn(DateUtils::dateTimeToTsRange($dropoff, 5));
        } else {
            $order
                ->getShippingTimeRange()
                ->willReturn(DateUtils::dateTimeToTsRange($pickup, 5));
        }

        $this->shippingTimeCalculator
            ->calculate($order->reveal())
            ->willReturn($shippingTime);

        $this->assertEquals($pickup, $this->resolver->resolve($order->reveal(), $dropoff));
    }
}
