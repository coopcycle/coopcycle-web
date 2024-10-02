<?php

namespace Tests\AppBundle\Entity\Sylius;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderTimeline;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class OrderTimelineTest extends TestCase
{
    use ProphecyTrait;

    public function testCreateForDelivery()
    {
        $order = $this->prophesize(Order::class);

        $order->getFulfillmentMethod()->willReturn('delivery');
        $order->setTimeline(Argument::type(OrderTimeline::class))->shouldNotBeCalled();

        $timeline = OrderTimeline::create(
            $order->reveal(),
            TsRange::create(
                new \DateTime('2020-01-23 19:30:00'),
                new \DateTime('2020-01-23 19:40:00')
            ),
            $preparationTime = '15 minutes',
            $shippingTime = '7 minutes'
        );

        $this->assertEquals(new \DateTime('2020-01-23 19:35:00'), $timeline->getDropoffExpectedAt());
        $this->assertEquals(new \DateTime('2020-01-23 19:23:00'), $timeline->getPickupExpectedAt());
        $this->assertEquals(new \DateTime('2020-01-23 19:08:00'), $timeline->getPreparationExpectedAt());
    }

    public function testCreateForCollection()
    {
        $order = $this->prophesize(Order::class);

        $order->getFulfillmentMethod()->willReturn('collection');
        $order->setTimeline(Argument::type(OrderTimeline::class))->shouldNotBeCalled();

        $timeline = OrderTimeline::create(
            $order->reveal(),
            TsRange::create(
                new \DateTime('2020-01-23 19:30:00'),
                new \DateTime('2020-01-23 19:40:00')
            ),
            $preparationTime = '15 minutes'
        );

        $this->assertNull($timeline->getDropoffExpectedAt());
        $this->assertEquals(new \DateTime('2020-01-23 19:35:00'), $timeline->getPickupExpectedAt());
        $this->assertEquals(new \DateTime('2020-01-23 19:20:00'), $timeline->getPreparationExpectedAt());
    }
}
