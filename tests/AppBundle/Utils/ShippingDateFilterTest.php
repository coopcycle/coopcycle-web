<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\PreparationTimeResolver;
use AppBundle\Utils\ShippingDateFilter;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class ShippingDateFilterTest extends TestCase
{
    private $restaurant;
    private $preparationTimeResolver;
    private $filter;

    public function setUp(): void
    {
        $this->restaurant = $this->prophesize(Restaurant::class);
        $this->preparationTimeResolver = $this->prophesize(PreparationTimeResolver::class);

        $this->filter = new ShippingDateFilter(
            $this->preparationTimeResolver->reveal()
        );
    }

    public function acceptProvider()
    {
        return [
            [
                // We want to order when the restaurant is closed
                $now = new \DateTime('2018-10-12 19:15:00'),
                $dropoff = new \DateTime('2018-10-14 12:30:00'),
                $preparation = new \DateTime('2018-10-14 12:05:00'),
                $isOpen = false,
                false,
            ],
            [
                // We want to order in the past
                $now = new \DateTime('2018-10-12 19:15:00'),
                $dropoff = new \DateTime('2018-10-12 19:00:00'),
                $preparation = new \DateTime('2018-10-12 18:30:00'),
                $isOpen = true,
                false,
            ],
            [
                // No problem
                $now = new \DateTime('2018-10-12 19:15:00'),
                $dropoff = new \DateTime('2018-10-12 19:45:00'),
                $preparation = new \DateTime('2018-10-12 19:30:00'),
                $isOpen = true,
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
        \DateTime $preparation,
        bool $isOpen,
        $expected)
    {
        $this->restaurant
            ->isOpen($preparation)
            ->willReturn($isOpen);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($this->restaurant->reveal());

        $this->preparationTimeResolver
            ->resolve($order->reveal(), $dropoff)
            ->willReturn($preparation);

        $this->assertEquals($expected, $this->filter->accept($order->reveal(), $dropoff, $now));
    }
}
