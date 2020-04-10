<?php

namespace Tests\AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\PreparationTimeResolver;
use AppBundle\Utils\PickupTimeResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class OrderTimelineCalculatorTest extends TestCase
{
    private $preparationTimeResolver;
    private $pickupTimeResolver;

    public function setUp(): void
    {
        $this->preparationTimeResolver = $this->prophesize(PreparationTimeResolver::class);
        $this->pickupTimeResolver = $this->prophesize(PickupTimeResolver::class);
    }

    private function createOrder(\DateTime $shippedAt)
    {
        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getShippingTimeRange()
            ->willReturn(DateUtils::dateTimeToTsRange($shippedAt, 5));

        return $order->reveal();
    }

    public function calculateProvider()
    {
        return [
            [
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:05:00'),
            ],
            [
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 13:00:00'),
            ],
            [
                new \DateTime('2018-08-25 13:30:00'),
                new \DateTime('2018-08-25 13:15:00'),
                new \DateTime('2018-08-25 12:45:00'),
            ],
        ];
    }

    /**
     * @dataProvider calculateProvider
     */
    public function testCalculate(
        \DateTime $dropoff,
        \DateTime $pickup,
        \DateTime $preparation)
    {
        $order = $this->createOrder($dropoff);

        $this->preparationTimeResolver
            ->resolve($order, $dropoff)
            ->willReturn($preparation);

        $this->pickupTimeResolver
            ->resolve($order, $dropoff)
            ->willReturn($pickup);

        $calculator = new OrderTimelineCalculator(
            $this->preparationTimeResolver->reveal(),
            $this->pickupTimeResolver->reveal()
        );

        $timeline = $calculator->calculate($order);

        $this->assertEquals($dropoff, $timeline->getDropoffExpectedAt());
        $this->assertEquals($pickup, $timeline->getPickupExpectedAt());
        $this->assertEquals($preparation, $timeline->getPreparationExpectedAt());
    }

    public function testDelay()
    {
        $timeline = new OrderTimeline();
        $timeline->setPreparationExpectedAt(new \DateTime('2020-04-09 19:30:00'));
        $timeline->setPickupExpectedAt(new \DateTime('2020-04-09 19:45:00'));
        $timeline->setDropoffExpectedAt(new \DateTime('2020-04-09 20:00:00'));

        $pickup = new Task();
        $pickup->setAfter(new \DateTime('2020-04-09 19:40:00'));
        $pickup->setBefore(new \DateTime('2020-04-09 19:50:00'));

        $dropoff = new Task();
        $dropoff->setAfter(new \DateTime('2020-04-09 19:55:00'));
        $dropoff->setBefore(new \DateTime('2020-04-09 20:05:00'));

        $delivery = $this->prophesize(Delivery::class);
        $delivery
            ->getTasks()
            ->willReturn([ $pickup, $dropoff ]);

        $order = $this->prophesize(Order::class);
        $order
            ->getTimeline()
            ->willReturn($timeline);
        $order
            ->getDelivery()
            ->willReturn($delivery->reveal());

        $calculator = new OrderTimelineCalculator(
            $this->preparationTimeResolver->reveal(),
            $this->pickupTimeResolver->reveal()
        );

        $calculator->delay($order->reveal(), 10);

        $this->assertEquals(new \DateTime('2020-04-09 19:40:00'), $timeline->getPreparationExpectedAt());
        $this->assertEquals(new \DateTime('2020-04-09 19:55:00'), $timeline->getPickupExpectedAt());
        $this->assertEquals(new \DateTime('2020-04-09 20:10:00'), $timeline->getDropoffExpectedAt());

        $order
            ->setShippingTimeRange(Argument::type(TsRange::class))
            ->shouldHaveBeenCalled();

        $this->assertEquals(new \DateTime('2020-04-09 19:50:00'), $pickup->getAfter());
        $this->assertEquals(new \DateTime('2020-04-09 20:00:00'), $pickup->getBefore());

        $this->assertEquals(new \DateTime('2020-04-09 20:05:00'), $dropoff->getAfter());
        $this->assertEquals(new \DateTime('2020-04-09 20:15:00'), $dropoff->getBefore());
    }
}
