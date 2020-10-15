<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderTarget;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ShippingTimeCalculator;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class OrderTimeHelperTest extends TestCase
{
    use ProphecyTrait;

    private $preparationTimeCalculator;
    private $shippingTimeCalculator;

    public function setUp(): void
    {
        $this->preparationTimeCalculator = $this->prophesize(PreparationTimeCalculator::class);
        $this->shippingDateFilter = $this->prophesize(ShippingDateFilter::class);
        $this->shippingTimeCalculator = $this->prophesize(ShippingTimeCalculator::class);

        $this->helper = new OrderTimeHelper(
            $this->shippingDateFilter->reveal(),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal(),
            'fr'
        );
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testAsapWithSameDayShippingChoices()
    {
        Carbon::setTestNow(Carbon::parse('2020-03-31T14:25:00+02:00'));

        $restaurant = $this->prophesize(LocalBusiness::class);

        $sameDayChoices = [
            '2020-03-31T14:30:00+02:00',
            '2020-03-31T14:45:00+02:00',
        ];

        $cart = $this->prophesize(OrderInterface::class);
        $cart
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $cart
            ->getTarget()
            ->willReturn(
                OrderTarget::withRestaurant($restaurant->reveal())
            );
        $cart
            ->getFulfillmentMethod()
            ->willReturn('delivery');

        $fulfillmentMethod = new FulfillmentMethod();
        $fulfillmentMethod->setOpeningHours(["Mo-Su 13:00-15:00"]);
        $fulfillmentMethod->setOpeningHoursBehavior('asap');

        $restaurant
            ->getShippingOptionsDays()
            ->willReturn(1);

        $restaurant
            ->getOpeningHours('delivery')
            ->willReturn($fulfillmentMethod->getOpeningHours());
        $restaurant
            ->getOpeningHoursBehavior('delivery')
            ->willReturn($fulfillmentMethod->getOpeningHoursBehavior());
        $restaurant
            ->getFulfillmentMethod('delivery')
            ->willReturn($fulfillmentMethod);

        $restaurant
            ->getOrderingDelayMinutes()
            ->willReturn(0);
        $restaurant
            ->getClosingRules()
            ->willReturn(new ArrayCollection());

        $this->shippingDateFilter
            ->accept($cart, Argument::type(\DateTime::class))
            ->will(function ($args) use ($sameDayChoices) {
                if (in_array($args[1]->format(\DateTime::ATOM), $sameDayChoices)) {
                    return false;
                }

                return true;
            });

        $shippingTimeRange = $this->helper->getShippingTimeRange($cart->reveal());

        $this->assertEquals(new \DateTime('2020-04-01 12:55:00'), $shippingTimeRange->getLower());
        $this->assertEquals(new \DateTime('2020-04-01 13:05:00'), $shippingTimeRange->getUpper());
    }
}
