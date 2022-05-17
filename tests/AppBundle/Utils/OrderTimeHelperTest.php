<?php

namespace Tests\AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\TimeRegistry;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ShippingTimeCalculator;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Redis;

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
        $this->redis = $this->prophesize(Redis::class);

        $this->timeRegistry = $this->prophesize(TimeRegistry::class);
        $this->timeRegistry->getAveragePreparationTime()->willReturn(0);
        $this->timeRegistry->getAverageShippingTime()->willReturn(0);

        $this->helper = new OrderTimeHelper(
            $this->shippingDateFilter->reveal(),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal(),
            $this->redis->reveal(),
            $this->timeRegistry->reveal(),
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
            '2020-03-31T14:40:00+02:00',
            '2020-03-31T14:50:00+02:00',
        ];

        $fulfillmentMethod = new FulfillmentMethod();
        $fulfillmentMethod->setOpeningHours(["Mo-Su 13:00-15:00"]);
        $fulfillmentMethod->setOpeningHoursBehavior('asap');
        $fulfillmentMethod->setOrderingDelayMinutes(0);

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
            ->getClosingRules()
            ->willReturn(new ArrayCollection());

        $cart = $this->prophesize(OrderInterface::class);
        $cart
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $cart
            ->getVendor()
            ->willReturn(
                Vendor::withRestaurant($restaurant->reveal())
            );
        $cart
            ->getFulfillmentMethod()
            ->willReturn('delivery');
        $cart
            ->getFulfillmentMethodObject()
            ->willReturn($fulfillmentMethod);

        $this->shippingDateFilter
            ->accept($cart, Argument::type(TsRange::class))
            ->will(function ($args) use ($sameDayChoices) {
                if (in_array($args[1]->getLower()->format(\DateTime::ATOM), $sameDayChoices)) {
                    return false;
                }

                return true;
            });

        $shippingTimeRange = $this->helper->getShippingTimeRange($cart->reveal());

        $this->assertEquals(new \DateTime('2020-04-01 13:00:00'), $shippingTimeRange->getLower());
        $this->assertEquals(new \DateTime('2020-04-01 13:10:00'), $shippingTimeRange->getUpper());
    }
}
