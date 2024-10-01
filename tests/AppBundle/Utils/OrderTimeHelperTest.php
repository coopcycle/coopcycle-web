<?php

namespace Tests\AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderVendor;
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
        $this->redis = self::$container->get(Redis::class);

        $this->timeRegistry = $this->prophesize(TimeRegistry::class);
        $this->timeRegistry->getAveragePreparationTime()->willReturn(0);
        $this->timeRegistry->getAverageShippingTime()->willReturn(0);

        $this->fulfillmentMethodResolver = $this->prophesize(FulfillmentMethodResolver::class);

        $this->helper = new OrderTimeHelper(
            self::$container->get(ShippingDateFilter::class),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal(),
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

    public function setUpFullfillmentMethodForTest($openingHoursArray, $priorNotice = 0) {
        $fulfillmentMethod = new FulfillmentMethod();
        $fulfillmentMethod->setOpeningHours($openingHoursArray);
        $fulfillmentMethod->setOpeningHoursBehavior('asap');
        $fulfillmentMethod->setOrderingDelayMinutes($priorNotice);

        return $fulfillmentMethod;
    }

    public function setUpRestaurantForTest($fulfillmentMethod) {

        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getId()
            ->willReturn(1);

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

        return $restaurant;
    }

    public function setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now) {

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
                $now
            );

        $this->fulfillmentMethodResolver
            ->resolveForOrder($cart)
            ->willReturn($fulfillmentMethod);

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

        return $cart;
    }

    public function setUpOrderVendorForTest($restaurant) {
        $orderVendor = $this->prophesize(OrderVendor::class);
        $orderVendor
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        return $orderVendor;
    }

    public function testAsapWithSameDayShippingChoices()
    {
        $now = Carbon::parse('2020-03-31T14:25:00+02:00');
        Carbon::setTestNow($now);

        $fulfillmentMethod = $this->setUpFullfillmentMethodForTest(["Mo-Su 13:00-15:00"]);

        $restaurant = $this->setUpRestaurantForTest($fulfillmentMethod);

        $orderVendor = $this->setUpOrderVendorForTest($restaurant); 

        $cart = $this->setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now);

        $shippingTimeRange = $this->helper->getShippingTimeRange($cart->reveal());
        
        $this->assertEquals(new \DateTime('2020-03-31 14:50:00'), $shippingTimeRange->getLower());
        $this->assertEquals(new \DateTime('2020-03-31 15:00:00'), $shippingTimeRange->getUpper());
    }

    public function testWith2DaysPriorNotice()
    {
        $now = Carbon::parse('2017-10-04T17:00:00+02:00');
        Carbon::setTestNow($now);

        $fulfillmentMethod = $this->setUpFullfillmentMethodForTest(["Mo-Su 09:00-19:00"], 2 * 24 * 60);

        $restaurant = $this->setUpRestaurantForTest($fulfillmentMethod);

        $orderVendor = $this->setUpOrderVendorForTest($restaurant); 

        $cart = $this->setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now);

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
    public function testWith90minPriorNotice()
    {
        $now = Carbon::parse('2021-01-27 17:08:00');
        Carbon::setTestNowAndTimezone($now);

        $fulfillmentMethod = $this->setUpFullfillmentMethodForTest(["Mo-Sa 11:30-14:00","Mo-Sa 18:00-20:30"], 90);

        $restaurant = $this->setUpRestaurantForTest($fulfillmentMethod);

        $orderVendor = $this->setUpOrderVendorForTest($restaurant); 

        $cart = $this->setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now);

        $shippingTimeRanges = $this->helper->getShippingTimeRanges($cart->reveal());
        $range = $shippingTimeRanges[0];
        
        // 19h05 for drop
        // -> -15min for pickup -> 18h50
        // -> 1h30 notice + 10min prep time -> 17h10 can start prep
        $this->assertEquals(new \DateTime('2021-01-27 19:00:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-01-27 19:10:00'), $range->getUpper());
    }

    public function testWith2HoursPriorNotice()
    {
        $now = Carbon::parse('2021-01-28 11:30:00');

        // It's a Thursday
        Carbon::setTestNowAndTimezone($now);

        $fulfillmentMethod = $this->setUpFullfillmentMethodForTest(["Mo-Fr 11:30-14:30"], 120);

        $restaurant = $this->setUpRestaurantForTest($fulfillmentMethod);

        $orderVendor = $this->setUpOrderVendorForTest($restaurant); 

        $cart = $this->setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now);

        $shippingTimeRanges = $this->helper->getShippingTimeRanges($cart->reveal());
        $range = $shippingTimeRanges[0];

        $this->assertEquals(new \DateTime('2021-01-28 14:00:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-01-28 14:10:00'), $range->getUpper());
    }

    public function testWith2HoursPriorNoticeProposesNextDayOpening()
    {
        $now = Carbon::parse('2021-01-28 12:30:00');

        Carbon::setTestNow($now);

        $fulfillmentMethod = $this->setUpFullfillmentMethodForTest(["Mo-Fr 11:30-14:30"], 120);

        $restaurant = $this->setUpRestaurantForTest($fulfillmentMethod);

        $orderVendor = $this->setUpOrderVendorForTest($restaurant); 

        $cart = $this->setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now);

        $shippingTimeRanges = $this->helper->getShippingTimeRanges($cart->reveal());
        $range = $shippingTimeRanges[0];
        
        // 120min delay means first slot is the first one tomorrow
        // 20min prep time starting at the opening
        $this->assertEquals(new \DateTime('2021-01-29 11:50:00'), $range->getLower());
        $this->assertEquals(new \DateTime('2021-01-29 12:00:00'), $range->getUpper());
    }

    public function testAsapWith40minPickupDelay()
    {   
        $now = Carbon::parse('2024-09-27T13:00:00+02:00');

        Carbon::setTestNow($now);

        $this->redis->set('foodtech:dispatch_delay_for_pickup', 40);

        $fulfillmentMethod = $this->setUpFullfillmentMethodForTest(["Mo-Su 13:00-15:00"]);

        $restaurant = $this->setUpRestaurantForTest($fulfillmentMethod);

        $orderVendor = $this->setUpOrderVendorForTest($restaurant); 

        $cart = $this->setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now);

        $shippingTimeRange = $this->helper->getShippingTimeRange($cart->reveal());

        // prep time is 20min (default)
        // shipping time is 10min (default)
        // pickup delay is 40min
        // 20min prep time removes 13:00 to 13:20 timeslots
        // 40min shipping time removes 13:00 to 13:40 timeslots
        $this->assertEquals(new \DateTime('2024-09-27 14:00:00'), $shippingTimeRange->getLower());
        $this->assertEquals(new \DateTime('2024-09-27 14:10:00'), $shippingTimeRange->getUpper());

        $this->redis->delete('foodtech:dispatch_delay_for_pickup');
    }

    public function testAsapWith20minPickupDelayDoesNotInfluence()
    {   
        $now = Carbon::parse('2024-09-27T13:00:00+02:00');

        Carbon::setTestNow($now);

        $this->redis->set('foodtech:dispatch_delay_for_pickup', 20);

        $fulfillmentMethod = $this->setUpFullfillmentMethodForTest(["Mo-Su 13:00-15:00"]);

        $restaurant = $this->setUpRestaurantForTest($fulfillmentMethod);

        $orderVendor = $this->setUpOrderVendorForTest($restaurant); 

        $cart = $this->setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now);

        $shippingTimeRange = $this->helper->getShippingTimeRange($cart->reveal());

        // prep time is 20min (default)
        // shipping time is 10min (default)
        // pickup delay is 20min
        // 20min prep time removes 13:00 to 13:20 timeslots
        // 20min shipping time removes 13:00 to 13:20 timeslots -> no effect
        $this->assertEquals(new \DateTime('2024-09-27 13:40:00'), $shippingTimeRange->getLower());
        $this->assertEquals(new \DateTime('2024-09-27 13:50:00'), $shippingTimeRange->getUpper());

        $this->redis->delete('foodtech:dispatch_delay_for_pickup');
    }

    public function testAsapWith40minPickupDelayAndInFirstHalfOfTimeslot()
    {   
        $now = Carbon::parse('2024-09-27T19:41:00+02:00');

        Carbon::setTestNow($now);

        $this->redis->set('foodtech:dispatch_delay_for_pickup', 35);

        $fulfillmentMethod = $this->setUpFullfillmentMethodForTest(["Mo-Su 19:00-22:00"]);

        $restaurant = $this->setUpRestaurantForTest($fulfillmentMethod);

        $orderVendor = $this->setUpOrderVendorForTest($restaurant); 

        $cart = $this->setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now);

        $shippingTimeRange = $this->helper->getShippingTimeRange($cart->reveal());

        // prep time is 20min (default)
        // shipping time is 10min (default)
        // pickup delay is 40min
        $this->assertEquals(new \DateTime('2024-09-27 20:30:00'), $shippingTimeRange->getLower());
        $this->assertEquals(new \DateTime('2024-09-27 20:40:00'), $shippingTimeRange->getUpper());

        $this->redis->delete('foodtech:dispatch_delay_for_pickup');
    }

    public function testAsapWith40minPickupDelayAndSecondHalfOfTimeslot()
    {   
        $now = Carbon::parse('2024-09-27T19:41:00+02:00');

        Carbon::setTestNow($now);

        $this->redis->set('foodtech:dispatch_delay_for_pickup', 40);

        $fulfillmentMethod = $this->setUpFullfillmentMethodForTest(["Mo-Su 19:00-22:00"]);

        $restaurant = $this->setUpRestaurantForTest($fulfillmentMethod);

        $orderVendor = $this->setUpOrderVendorForTest($restaurant); 

        $cart = $this->setUpCartForTest($fulfillmentMethod, $restaurant, $orderVendor, $now);

        $shippingTimeRange = $this->helper->getShippingTimeRange($cart->reveal());

        // prep time is 20min (default)
        // shipping time is 10min (default)
        // pickup delay is 40min
        $this->assertEquals(new \DateTime('2024-09-27 20:40:00'), $shippingTimeRange->getLower());
        $this->assertEquals(new \DateTime('2024-09-27 20:50:00'), $shippingTimeRange->getUpper());

        $this->redis->delete('foodtech:dispatch_delay_for_pickup');
    }
}
