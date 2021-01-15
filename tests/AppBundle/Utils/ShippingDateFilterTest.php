<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Vendor;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\PreparationTimeResolver;
use AppBundle\Utils\ShippingDateFilter;
use Doctrine\Common\Collections\ArrayCollection;
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
                $closingRules = [],
                false,
            ],
            [
                // $dropoff < $now
                $now = new \DateTime('2018-10-12 12:00:00'),
                $dropoff = new \DateTime('2018-10-12 11:55:00'),
                $preparation = new \DateTime('2018-10-12 11:45:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [],
                false,
            ],
            [
                // $preparation < $now
                $now = new \DateTime('2018-10-12 12:00:00'),
                $dropoff = new \DateTime('2018-10-12 12:05:00'),
                $preparation = new \DateTime('2018-10-12 11:50:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [],
                false,
            ],
            [
                // closing rule
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = new \DateTime('2018-10-12 12:45:00'),
                $preparation = new \DateTime('2018-10-12 12:30:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [
                    ['2018-10-12 12:00:00', '2018-10-12 13:00:00']
                ],
                false,
            ],
            [
                // More than 7 days
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = new \DateTime('2018-10-19 11:30:00'),
                $preparation = new \DateTime('2018-10-12 12:30:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [],
                false,
            ],
            [
                // No problem
                $now = new \DateTime('2018-10-12 11:00:00'),
                $dropoff = new \DateTime('2018-10-12 12:45:00'),
                $preparation = new \DateTime('2018-10-12 12:30:00'),
                $openingHours = ['Mo-Su 11:30-14:30'],
                $closingRules = [],
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
        array $closingRules,
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

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($this->restaurant->reveal())
            );
        $order
            ->getFulfillmentMethod()
            ->willReturn('delivery');

        $tsRange = DateUtils::dateTimeToTsRange($dropoff, 5);

        $this->preparationTimeResolver
            ->resolve($order->reveal(), $tsRange->getUpper())
            ->willReturn($preparation);

        $this->assertEquals($expected, $this->filter->accept($order->reveal(), $tsRange, $now));
    }
}
