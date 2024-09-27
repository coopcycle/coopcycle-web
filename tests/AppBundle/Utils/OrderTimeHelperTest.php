<?php

namespace Tests\AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderVendor;
use AppBundle\Form\Type\TsRangeChoice;
use AppBundle\Fulfillment\FulfillmentMethodResolver;
use AppBundle\Service\NullLoggingUtils;
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
use Psr\Log\NullLogger;
use Redis;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderTimeHelperTest extends KernelTestCase
{
    use ProphecyTrait;

    private $preparationTimeCalculator;
    private $shippingTimeCalculator;
    private $redis;
    private $shippingDateFilter;
    private $timeRegistry;
    private $fulfillmentMethodResolver;
    private $helper;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->preparationTimeCalculator = $this->prophesize(PreparationTimeCalculator::class);
        $this->shippingDateFilter = $this->prophesize(ShippingDateFilter::class);
        $this->shippingTimeCalculator = $this->prophesize(ShippingTimeCalculator::class);
        $this->redis = $this->prophesize(Redis::class);

        $this->timeRegistry = $this->prophesize(TimeRegistry::class);
        $this->timeRegistry->getAveragePreparationTime()->willReturn(0);
        $this->timeRegistry->getAverageShippingTime()->willReturn(0);

        $this->fulfillmentMethodResolver = $this->prophesize(FulfillmentMethodResolver::class);

        $this->helper = new OrderTimeHelper(
            self::$container->get(ShippingDateFilter::class),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal(),
            $this->redis->reveal(),
            $this->timeRegistry->reveal(),
            $this->fulfillmentMethodResolver->reveal(),
            'fr',
            new NullLogger(),
            new NullLoggingUtils()
        );
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    private function rangeToString(TsRange $range)
    {
        return $range->getLower()->format('Y-m-d H:i').' - '.$range->getUpper()->format('Y-m-d H:i');
    }

    private function assertContainsTimeRange(TsRange $range, array $dates)
    {
        $toString = array_map(function (TsRange $range) {
            return $this->rangeToString($range);
        }, $dates);

        $this->assertContains($this->rangeToString($range), $toString);
    }

    private function assertContainsTimeRanges(array $expected, array $timeRanges)
    {
        $expected = array_map(function ($date) {
            $range = new TsRange();
            $range->setLower(new \DateTime($date[0]));
            $range->setUpper(new \DateTime($date[1]));

            return $range;
        }, $expected);

        foreach ($expected as $value) {
            $this->assertContainsTimeRange($value, $timeRanges);
        }
    }

    private function assertNotContainsTimeRanges(array $expected, array $timeRanges)
    {
        $strings = array_map(function ($date) {
            $range = new TsRange();
            $range->setLower(new \DateTime($date[0]));
            $range->setUpper(new \DateTime($date[1]));

            return $this->rangeToString($range);
        }, $expected);

        $choicesAsString = array_map(function (TsRange $range) {
            return $this->rangeToString($range);
        }, $timeRanges);

        foreach ($strings as $string) {
            $this->assertNotContains($string, $choicesAsString);
        }
    }

    public function testAsapWithSameDayShippingChoices()
    {
        Carbon::setTestNow(Carbon::parse('2020-03-31T14:25:00+02:00'));

        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getId()
            ->willReturn(1);

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
        $restaurant
            ->getRateLimitAmount()
            ->willReturn(false);

        $cart = $this->prophesize(OrderInterface::class);
        $cart
            ->getId()
            ->willReturn(null);
        $cart
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $cart
            ->getRestaurants()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getPickupAddresses()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getShippingAddress()
            ->willReturn(null);
        $cart
            ->getVendorConditions()
            ->willReturn(
                $restaurant->reveal()
            );
        $cart
            ->getCreatedAt()
            ->willReturn(
                new \DateTime('2017-10-04 17:00:00')
            );

        $this->fulfillmentMethodResolver
            ->resolveForOrder($cart)
            ->willReturn($fulfillmentMethod);

        $orderVendor = $this->prophesize(OrderVendor::class);
        $orderVendor
            ->getRestaurant()
            ->willReturn($restaurant->reveal());

        $cart
            ->getVendors()
            ->willReturn(
                new ArrayCollection([ $orderVendor->reveal() ])
            );
        $cart
            ->hasVendor()
            ->willReturn(
                true
            );
        $cart
            ->isMultiVendor()
            ->willReturn(
                false
            );

        $cart
            ->getFulfillmentMethod()
            ->willReturn('delivery');

        $shippingTimeRange = $this->helper->getShippingTimeRange($cart->reveal());

        $this->assertEquals(new \DateTime('2020-04-01 13:30:00'), $shippingTimeRange->getLower());
        $this->assertEquals(new \DateTime('2020-04-01 13:40:00'), $shippingTimeRange->getUpper());
    }

    public function testWithDelayedOrders()
    {
        Carbon::setTestNow(Carbon::parse('2017-10-04T17:00:00+02:00'));

        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getId()
            ->willReturn(1);

        $fulfillmentMethod = new FulfillmentMethod();
        $fulfillmentMethod->setOpeningHours(["Mo-Su 09:00-19:00"]);
        $fulfillmentMethod->setOpeningHoursBehavior('asap');
        $fulfillmentMethod->setOrderingDelayMinutes(2 * 24 * 60); // should order two days in advance;

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
        $restaurant
            ->getRateLimitAmount()
            ->willReturn(false);

        $cart = $this->prophesize(OrderInterface::class);
        $cart
            ->getId()
            ->willReturn(null);
        $cart
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $cart
            ->getRestaurants()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getPickupAddresses()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getShippingAddress()
            ->willReturn(null);
        $cart
            ->getVendorConditions()
            ->willReturn(
                $restaurant->reveal()
            );
        $cart
            ->getCreatedAt()
            ->willReturn(
                new \DateTime('2017-10-04 17:00:00')
            );

        $orderVendor = $this->prophesize(OrderVendor::class);
        $orderVendor
            ->getRestaurant()
            ->willReturn($restaurant->reveal());

        $cart
            ->getVendors()
            ->willReturn(
                new ArrayCollection([ $orderVendor->reveal() ])
            );
        $cart
            ->hasVendor()
            ->willReturn(
                true
            );
        $cart
            ->isMultiVendor()
            ->willReturn(
                false
            );

        $cart
            ->getFulfillmentMethod()
            ->willReturn('delivery');

        $this->fulfillmentMethodResolver
            ->resolveForOrder($cart)
            ->willReturn($fulfillmentMethod);

        $shippingTimeRanges = $this->helper->getShippingTimeRanges($cart->reveal());

        $this->assertContainsTimeRanges([
            [ '2017-10-06T17:30:00+02:00', '2017-10-06T17:40:00+02:00'],
            [ '2017-10-07T10:00:00+02:00', '2017-10-07T10:10:00+02:00' ],
            [ '2017-10-07T18:50:00+02:00', '2017-10-07T19:00:00+02:00' ],
        ], $shippingTimeRanges);

        $this->assertNotContainsTimeRanges([
            ['2017-10-04T17:40:00+02:00', '2017-10-04T17:50:00+02:00'],
            ['2017-10-04T18:00:00+02:00', '2017-10-04T18:10:00+02:00'],
            ['2017-10-05T17:40:00+02:00', '2017-10-05T17:50:00+02:00']
        ], $shippingTimeRanges);

    }
    public function testEscaleDuJapon()
    {
        // It's a Wednesday
        Carbon::setTestNowAndTimezone(Carbon::parse('2021-01-27 17:08:00'));

        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getId()
            ->willReturn(1);

        $fulfillmentMethod = new FulfillmentMethod();
        $fulfillmentMethod->setOpeningHours(["Mo-Sa 11:30-14:00","Mo-Sa 18:00-20:30"]);
        $fulfillmentMethod->setOpeningHoursBehavior('asap');
        $fulfillmentMethod->setOrderingDelayMinutes(90); // delay of 1h30

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
        $restaurant
            ->getRateLimitAmount()
            ->willReturn(false);

        $cart = $this->prophesize(OrderInterface::class);
        $cart
            ->getId()
            ->willReturn(null);
        $cart
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $cart
            ->getRestaurants()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getPickupAddresses()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getShippingAddress()
            ->willReturn(null);
        $cart
            ->getVendorConditions()
            ->willReturn(
                $restaurant->reveal()
            );
        $cart
            ->getCreatedAt()
            ->willReturn(
                new \DateTime('2021-01-27 17:08:00')
            );

        $orderVendor = $this->prophesize(OrderVendor::class);
        $orderVendor
            ->getRestaurant()
            ->willReturn($restaurant->reveal());

        $cart
            ->getVendors()
            ->willReturn(
                new ArrayCollection([ $orderVendor->reveal() ])
            );
        $cart
            ->hasVendor()
            ->willReturn(
                true
            );
        $cart
            ->isMultiVendor()
            ->willReturn(
                false
            );

        $cart
            ->getFulfillmentMethod()
            ->willReturn('delivery');

        $this->fulfillmentMethodResolver
            ->resolveForOrder($cart)
            ->willReturn($fulfillmentMethod);

        $shippingTimeRanges = $this->helper->getShippingTimeRanges($cart->reveal());
        $range = $shippingTimeRanges[0];
        
        // 20min prep time and pickup 25min before drop
        // 90min delay for the pickup
        // -> 115min later
        $this->assertEquals(new \DateTime('2021-01-27 19:10:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-01-27 19:20:00'), $range->getUpper());
    }

    public function testWith2HoursDelay()
    {
        // It's a Thursday
        Carbon::setTestNowAndTimezone(Carbon::parse('2021-01-28 11:30:00'));

        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getId()
            ->willReturn(1);

        $fulfillmentMethod = new FulfillmentMethod();
        $fulfillmentMethod->setOpeningHours(["Mo-Fr 11:30-14:30"]);
        $fulfillmentMethod->setOpeningHoursBehavior('asap');
        $fulfillmentMethod->setOrderingDelayMinutes(120); // delay of 2h

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
        $restaurant
            ->getRateLimitAmount()
            ->willReturn(false);

        $cart = $this->prophesize(OrderInterface::class);
        $cart
            ->getId()
            ->willReturn(null);
        $cart
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $cart
            ->getRestaurants()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getPickupAddresses()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getShippingAddress()
            ->willReturn(null);
        $cart
            ->getVendorConditions()
            ->willReturn(
                $restaurant->reveal()
            );
        $cart
            ->getCreatedAt()
            ->willReturn(
                new \DateTime('2021-01-28 11:30:00')
            );

        $orderVendor = $this->prophesize(OrderVendor::class);
        $orderVendor
            ->getRestaurant()
            ->willReturn($restaurant->reveal());

        $cart
            ->getVendors()
            ->willReturn(
                new ArrayCollection([ $orderVendor->reveal() ])
            );
        $cart
            ->hasVendor()
            ->willReturn(
                true
            );
        $cart
            ->isMultiVendor()
            ->willReturn(
                false
            );

        $cart
            ->getFulfillmentMethod()
            ->willReturn('delivery');

        $this->fulfillmentMethodResolver
            ->resolveForOrder($cart)
            ->willReturn($fulfillmentMethod);

        $shippingTimeRanges = $this->helper->getShippingTimeRanges($cart->reveal());
        $range = $shippingTimeRanges[0];

        $this->assertEquals(new \DateTime('2021-01-28 14:00:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-01-28 14:10:00'), $range->getUpper());
    }

    public function testWith2HoursDelayProposesNextDayOpening()
    {
         $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getId()
            ->willReturn(1);

        $fulfillmentMethod = new FulfillmentMethod();
        $fulfillmentMethod->setOpeningHours(["Mo-Fr 11:30-14:30"]);
        $fulfillmentMethod->setOpeningHoursBehavior('asap');
        $fulfillmentMethod->setOrderingDelayMinutes(120); // delay of 2h

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
        $restaurant
            ->getRateLimitAmount()
            ->willReturn(false);

        $cart = $this->prophesize(OrderInterface::class);
        $cart
            ->getId()
            ->willReturn(null);
        $cart
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $cart
            ->getRestaurants()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getPickupAddresses()
            ->willReturn(new ArrayCollection([]));
        $cart
            ->getShippingAddress()
            ->willReturn(null);
        $cart
            ->getVendorConditions()
            ->willReturn(
                $restaurant->reveal()
            );
        $cart
            ->getCreatedAt()
            ->willReturn(
                new \DateTime('2021-01-28 11:30:00')
            );

        $orderVendor = $this->prophesize(OrderVendor::class);
        $orderVendor
            ->getRestaurant()
            ->willReturn($restaurant->reveal());

        $cart
            ->getVendors()
            ->willReturn(
                new ArrayCollection([ $orderVendor->reveal() ])
            );
        $cart
            ->hasVendor()
            ->willReturn(
                true
            );
        $cart
            ->isMultiVendor()
            ->willReturn(
                false
            );

        $cart
            ->getFulfillmentMethod()
            ->willReturn('delivery');

        $this->fulfillmentMethodResolver
            ->resolveForOrder($cart)
            ->willReturn($fulfillmentMethod);

        Carbon::setTestNowAndTimezone(Carbon::parse('2021-01-28 12:30:00'));

        $shippingTimeRanges = $this->helper->getShippingTimeRanges($cart->reveal());
        $range = $shippingTimeRanges[0];
        
        // 120min delay means first slot is the first one tomorrow
        // 20min prep time starting at the opening
        $this->assertEquals(new \DateTime('2021-01-29 12:00:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-01-29 12:10:00'), $range->getUpper());
    }
}
