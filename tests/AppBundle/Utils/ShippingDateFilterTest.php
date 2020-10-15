<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderTarget;
use AppBundle\Utils\PreparationTimeResolver;
use AppBundle\Utils\ShippingDateFilter;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class ShippingDateFilterTest extends TestCase
{
    use ProphecyTrait;

    private $restaurant;
    private $preparationTimeResolver;
    private $filter;

    public function setUp(): void
    {
        $this->restaurant = $this->prophesize(LocalBusiness::class);
        $this->preparationTimeResolver = $this->prophesize(PreparationTimeResolver::class);

        $this->filter = new ShippingDateFilter(
            $this->preparationTimeResolver->reveal()
        );
    }

    public function acceptProvider()
    {
        return [
            [
                // $preparation = 11:15, restaurant is closed
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = new \DateTime('2018-10-12 11:30:00'),
                $preparation = new \DateTime('2018-10-12 11:15:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $hasClosingRuleForNow = false,
                false,
            ],
            [
                // $dropoff < $now
                $now = new \DateTime('2018-10-12 12:00:00'),
                $dropoff = new \DateTime('2018-10-12 11:55:00'),
                $preparation = new \DateTime('2018-10-12 11:45:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $hasClosingRuleForNow = false,
                false,
            ],
            [
                // $preparation < $now
                $now = new \DateTime('2018-10-12 12:00:00'),
                $dropoff = new \DateTime('2018-10-12 12:05:00'),
                $preparation = new \DateTime('2018-10-12 11:50:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $hasClosingRuleForNow = false,
                false,
            ],
            [
                // closing rule
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = new \DateTime('2018-10-12 12:45:00'),
                $preparation = new \DateTime('2018-10-12 12:30:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $hasClosingRuleForNow = true,
                false,
            ],
            [
                // No problem
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = new \DateTime('2018-10-12 12:45:00'),
                $preparation = new \DateTime('2018-10-12 12:30:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $hasClosingRuleForNow = false,
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
        array $openingHours,
        bool $hasClosingRuleForNow,
        $expected)
    {
        $this->restaurant
            ->hasClosingRuleFor($preparation, Argument::any())
            ->willReturn($hasClosingRuleForNow);

        $this->restaurant
            ->getOpeningHours('delivery')
            ->willReturn($openingHours);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getTarget()
            ->willReturn(
                OrderTarget::withRestaurant($this->restaurant->reveal())
            );
        $order
            ->getFulfillmentMethod()
            ->willReturn('delivery');

        $this->preparationTimeResolver
            ->resolve($order->reveal(), $dropoff)
            ->willReturn($preparation);

        $this->assertEquals($expected, $this->filter->accept($order->reveal(), $dropoff, $now));
    }
}
