<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use PHPUnit\Framework\TestCase;
use Predis\Client as Redis;
use Prophecy\Argument;

class ShippingDateFilterTest extends TestCase
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

        $this->shippingDateFilter = new ShippingDateFilter(
            $this->redis->reveal(),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal()
        );
    }

    public function testDateInThePast()
    {
        $order = $this->prophesize(OrderInterface::class);

        $shippingDate = new \DateTime('2018-10-12 19:30:00');
        $now = new \DateTime('2018-10-12 20:00:00');

        $this->assertFalse($this->shippingDateFilter->accept($order->reveal(), $shippingDate, $now));
    }

    public function acceptWhenRestaurantIsOpenProvider()
    {
        return [
            [
                new \DateTime('2018-10-12 19:15:00'),
                new \DateTime('2018-10-12 19:40:00'),
                '15 minutes',
                '10 minutes',
                null,
                false,
            ],
            [
                new \DateTime('2018-10-12 19:25:00'),
                new \DateTime('2018-10-12 19:40:00'),
                '10 minutes',
                '10 minutes',
                null,
                false,
            ],
            [
                new \DateTime('2018-10-12 19:15:00'),
                new \DateTime('2018-10-12 19:55:00'),
                '15 minutes',
                '10 minutes',
                '15',
                false,
            ],
            [
                new \DateTime('2018-10-12 19:25:00'),
                new \DateTime('2018-10-12 19:55:00'),
                '15 minutes',
                '10 minutes',
                null,
                true,
            ],
            [
                new \DateTime('2018-10-12 19:25:00'),
                new \DateTime('2018-10-12 19:55:00'),
                '15 minutes',
                '10 minutes',
                '0',
                true,
            ],
            [
                new \DateTime('2018-10-12 19:25:00'),
                new \DateTime('2018-10-12 20:25:00'),
                '15 minutes',
                '10 minutes',
                '30',
                true,
            ],
        ];
    }

    /**
     * @dataProvider acceptWhenRestaurantIsOpenProvider
     */
    public function testAcceptWhenRestaurantIsOpen(
        \DateTime $now,
        \DateTime $shippingDate,
        $preparationTime,
        $shippingTime,
        $preparationDelay,
        $expected)
    {
        $this->restaurant
            ->isOpen($now)
            ->willReturn(true);

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

        $this->assertEquals($expected, $this->shippingDateFilter->accept($order->reveal(), $shippingDate, $now));
    }

    public function acceptWhenRestaurantIsClosedProvider()
    {
        return [
            [
                new \DateTime('2018-10-12 13:15:00'),
                '15 minutes',
                '10 minutes',
                new \DateTime('2018-10-12 19:00:00'),
                new \DateTime('2018-10-12 19:20:00'),
                false,
            ],
            [
                new \DateTime('2018-10-12 13:15:00'),
                '15 minutes',
                '10 minutes',
                new \DateTime('2018-10-12 19:00:00'),
                new \DateTime('2018-10-12 19:30:00'),
                true,
            ],
        ];
    }

    /**
     * @dataProvider acceptWhenRestaurantIsClosedProvider
     */
    public function testAcceptWhenRestaurantIsClosed(
        \DateTime $now,
        $preparationTime,
        $shippingTime,
        \DateTime $nextOpeningDate,
        \DateTime $shippingDate,
        $expected)
    {
        $this->restaurant
            ->isOpen($now)
            ->willReturn(false);

        $this->restaurant
            ->getNextOpeningDate($now)
            ->willReturn($nextOpeningDate);

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

        $this->assertEquals($expected, $this->shippingDateFilter->accept($order->reveal(), $shippingDate, $now));
    }
}
