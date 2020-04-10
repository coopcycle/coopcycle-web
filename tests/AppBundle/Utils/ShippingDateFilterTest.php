<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\PickupTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class ShippingDateFilterTest extends TestCase
{
    private $restaurant;
    private $pickupTimeCalculator;
    private $filter;

    public function setUp(): void
    {
        $this->restaurant = $this->prophesize(Restaurant::class);
        $this->pickupTimeCalculator = $this->prophesize(PickupTimeCalculator::class);

        $this->filter = new ShippingDateFilter(
            $this->pickupTimeCalculator->reveal()
        );
    }

    public function acceptProvider()
    {
        return [
            [
                // We want to order when the restaurant is closed
                $now = new \DateTime('2018-10-12 19:15:00'),
                $dropoff = new \DateTime('2018-10-14 12:30:00'),
                $pickup = new \DateTime('2018-10-14 12:05:00'),
                $isOpenForPickup = false,
                false,
            ],
            [
                // We want to order in the past
                $now = new \DateTime('2018-10-12 19:15:00'),
                $dropoff = new \DateTime('2018-10-12 19:00:00'),
                $pickup = new \DateTime('2018-10-12 18:30:00'),
                $isOpenForPickup = true,
                false,
            ],
            [
                // No problem
                $now = new \DateTime('2018-10-12 19:15:00'),
                $dropoff = new \DateTime('2018-10-12 19:45:00'),
                $pickup = new \DateTime('2018-10-12 19:30:00'),
                $isOpenForPickup = true,
                true,
            ],
        ];
    }

    /**
     * @dataProvider acceptProvider
     */
    public function testAccept(
        \DateTime $now,
        \DateTime $dropoff,
        \DateTime $pickup,
        bool $isOpenForPickup,
        $expected)
    {
        $this->restaurant
            ->isOpen($pickup)
            ->willReturn($isOpenForPickup);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($this->restaurant->reveal());

        $this->pickupTimeCalculator
            ->calculate($order->reveal(), $dropoff)
            ->willReturn($pickup);

        $this->assertEquals($expected, $this->filter->accept($order->reveal(), $dropoff, $now));
    }
}
