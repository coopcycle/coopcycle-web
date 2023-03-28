<?php

namespace Tests\AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Entity\Vendor;
use AppBundle\Utils\OrdersRateLimit;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\ShippingDateFilter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class ShippingDateFilterTest extends TestCase
{
    use ProphecyTrait;

    private $restaurant;
    private $orderTimelineCalculator;
    private $filter;

    public function setUp(): void
    {
        $this->restaurant = $this->prophesize(LocalBusiness::class);
        $this->orderTimelineCalculator = $this->prophesize(OrderTimelineCalculator::class);
        $this->rateLimiter = $this->prophesize(OrdersRateLimit::class);

        $this->filter = new ShippingDateFilter(
            $this->orderTimelineCalculator->reveal(),
            $this->rateLimiter->reveal()
        );
    }

    public function acceptProvider()
    {
        return [
            [
                // $preparation = 11:15, restaurant is closed
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = TsRange::create(
                    new \DateTime('2018-10-12 11:25:00'),
                    new \DateTime('2018-10-12 11:35:00')
                ),
                $preparation = new \DateTime('2018-10-12 11:15:00'),
                $pickup = new \DateTime('2018-10-12 11:25:00'),
                $preparationTime = '10 minutes',
                $shippingTime = '10 minutes',
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [],
                $isRangeFull = false,
                false,
            ],
            [
                // $dropoff < $now
                $now = new \DateTime('2018-10-12 12:00:00'),
                $dropoff = TsRange::create(
                    new \DateTime('2018-10-12 11:50:00'),
                    new \DateTime('2018-10-12 12:00:00')
                ),
                $preparation = new \DateTime('2018-10-12 11:45:00'),
                $pickup = new \DateTime('2018-10-12 11:55:00'),
                $preparationTime = '10 minutes',
                $shippingTime = '10 minutes',
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [],
                $isRangeFull = false,
                false,
            ],
            [
                // $preparation < $now
                $now = new \DateTime('2018-10-12 12:00:00'),
                $dropoff = TsRange::create(
                    new \DateTime('2018-10-12 12:00:00'),
                    new \DateTime('2018-10-12 12:10:00')
                ),
                $preparation = new \DateTime('2018-10-12 11:50:00'),
                $pickup = new \DateTime('2018-10-12 12:00:00'),
                $preparationTime = '10 minutes',
                $shippingTime = '10 minutes',
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [],
                $isRangeFull = false,
                false,
            ],
            [
                // closing rule
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = TsRange::create(
                    new \DateTime('2018-10-12 12:40:00'),
                    new \DateTime('2018-10-12 12:50:00')
                ),
                $preparation = new \DateTime('2018-10-12 12:30:00'),
                $pickup = new \DateTime('2018-10-12 12:40:00'),
                $preparationTime = '10 minutes',
                $shippingTime = '10 minutes',
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [
                    ['2018-10-12 12:00:00', '2018-10-12 13:00:00']
                ],
                $isRangeFull = false,
                false,
            ],
            [
                // More than 7 days
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = TsRange::create(
                    new \DateTime('2018-10-19 11:25:00'),
                    new \DateTime('2018-10-19 11:35:00')
                ),
                $preparation = new \DateTime('2018-10-12 11:15:00'),
                $pickup = new \DateTime('2018-10-12 11:25:00'),
                $preparationTime = '10 minutes',
                $shippingTime = '10 minutes',
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [],
                $isRangeFull = false,
                false,
            ],
            [
                // No problem
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = TsRange::create(
                    new \DateTime('2018-10-12 11:40:00'),
                    new \DateTime('2018-10-12 12:50:00')
                ),
                $preparation = new \DateTime('2018-10-12 12:30:00'),
                $pickup = new \DateTime('2018-10-12 11:40:00'),
                $preparationTime = '10 minutes',
                $shippingTime = '10 minutes',
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [],
                $isRangeFull = false,
                true,
            ],
        ];
    }

    /**
     * @dataProvider acceptProvider
     */
    public function testAccept(
        \DateTime $now,
        TsRange $tsRange,
        \DateTime $preparation,
        \DateTime $pickup,
        string $preparationTime,
        string $shippingTime,
        array $openingHours,
        array $closingRules,
        bool $isRangeFull,
        $expected)
    {
        $restaurantClosingRules = new ArrayCollection();
        foreach ($closingRules as $rule) {
            $closingRule = new ClosingRule();
            $closingRule->setStartDate(new \DateTime($rule[0]));
            $closingRule->setEndDate(new \DateTime($rule[1]));
            $restaurantClosingRules->add($closingRule);
        }

        $this->restaurant
            ->getClosingRules()
            ->willReturn($restaurantClosingRules);

        $this->restaurant
            ->getOpeningHours('delivery')
            ->willReturn($openingHours);

        $order = $this->prophesize(Order::class);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($this->restaurant->reveal())
            );
        $order
            ->getFulfillmentMethod()
            ->willReturn('delivery');

        $timeline = new OrderTimeline();
        $timeline->setPreparationTime($preparationTime);
        $timeline->setShippingTime($shippingTime);
        $timeline->setPreparationExpectedAt($preparation);
        $timeline->setPickupExpectedAt($pickup);

        $this->orderTimelineCalculator
            ->calculate($order->reveal(), $tsRange)
            ->willReturn($timeline);

        $this->rateLimiter
            ->isRangeFull($order, $pickup)
            ->willReturn($isRangeFull);

        $this->assertEquals($expected, $this->filter->accept($order->reveal(), $tsRange, $now));
    }
}
