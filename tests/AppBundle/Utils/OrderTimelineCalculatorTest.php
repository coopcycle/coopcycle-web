<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\PreparationTimeResolver;
use AppBundle\Utils\PickupTimeResolver;
use PHPUnit\Framework\TestCase;

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
            ->getShippedAt()
            ->willReturn($shippedAt);

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

        $this->calculator = new OrderTimelineCalculator(
            $this->preparationTimeResolver->reveal(),
            $this->pickupTimeResolver->reveal()
        );

        $timeline = $this->calculator->calculate($order);

        $this->assertEquals($dropoff, $timeline->getDropoffExpectedAt());
        $this->assertEquals($pickup, $timeline->getPickupExpectedAt());
        $this->assertEquals($preparation, $timeline->getPreparationExpectedAt());
    }
}
