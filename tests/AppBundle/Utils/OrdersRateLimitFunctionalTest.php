<?php

namespace AppBundle\Utils;

use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderDelayed;
use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Utils\OrdersRateLimit;
use Carbon\Carbon;
use Prophecy\PhpUnit\ProphecyTrait;
use Redis;
use Prophecy\Argument;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrdersRateLimitFunctionalTest extends KernelTestCase
{
	use ProphecyTrait;

    private $redis;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        Carbon::setTestNow(Carbon::parse('2023-03-28 12:30:00'));

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->redis = self::$container->get(Redis::class);

        $this->rateLimiter = new OrdersRateLimit(
            $this->redis,
            new NullLogger()
        );

        // Cleanup keys
        $prefix = $this->redis->getOption(Redis::OPT_PREFIX);
        $this->redis->delete(array_map(
		    function ($key) use ($prefix) {
		        return str_replace($prefix, '', $key);
		    }, $this->redis->keys('*rate_limit*'))
		);
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    private function createOrder(int $id, LocalBusiness $restaurant, \DateTime $pickupExpectedAt)
    {
    	$timeline = $this->prophesize(OrderTimeline::class);
    	$timeline->getPickupExpectedAt()->willReturn($pickupExpectedAt);

    	$order = $this->prophesize(Order::class);
    	$order->getId()->willReturn($id);
        $order->hasVendor()->willReturn(true);
    	$order->isMultiVendor()->willReturn(false);
    	$order->getRestaurant()->willReturn($restaurant);
    	$order->getTimeline()->willReturn($timeline->reveal());

    	return $order->reveal();
    }

    public function testHandleOrderCreatedEvent()
    {

    	$orders = [
    		new \DateTime('2023-03-28 12:05:00'),
    		new \DateTime('2023-03-28 12:05:00'),
    		new \DateTime('2023-03-28 12:06:00'),
    		new \DateTime('2023-03-28 12:06:00'),
    		new \DateTime('2023-03-28 12:07:00'),
    		new \DateTime('2023-03-28 12:07:00'),
    		new \DateTime('2023-03-28 12:08:00'),
    		new \DateTime('2023-03-28 12:08:00'),
    		new \DateTime('2023-03-28 12:09:00'),
    		new \DateTime('2023-03-28 12:10:00'),
    	];

    	$restaurant = $this->prophesize(LocalBusiness::class);
    	$restaurant->getId()->willReturn(1);

        // We want a max 10 orders per 30 minutes
    	$restaurant->getRateLimitAmount()->willReturn(10);
    	$restaurant->getRateLimitRangeDuration()->willReturn(30);

    	foreach ($orders as $i => $pickupExpectedAt) {

	    	$order = $this->createOrder(($i + 1), $restaurant->reveal(), $pickupExpectedAt);
	    	$orderCreated = new OrderCreated($order);

	    	$this->rateLimiter->handleEvent($orderCreated);
    	}

    	$this->assertEquals(1, $this->redis->exists('rate_limit:1'));

        $anotherOrderPickup = new \DateTime('2023-03-28 12:05:00');
    	$anotherOrder = $this->createOrder(($i + 1), $restaurant->reveal(), $anotherOrderPickup);

    	$this->assertEquals(true, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup));

        $anotherOrderPickup = new \DateTime('2023-03-28 12:36:00');
        $anotherOrder = $this->createOrder(($i + 1), $restaurant->reveal(), $anotherOrderPickup);

        $this->assertEquals(false, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup));
    }

    public function testHandleOrderDelayedEvent()
    {
        $this->markTestSkipped();

        $orders = [
            new \DateTime('2023-03-28 12:05:00'),
            new \DateTime('2023-03-28 12:05:00'),
            new \DateTime('2023-03-28 12:38:00'),
        ];

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getId()->willReturn(1);

        // We want a max 10 orders per 30 minutes
        $restaurant->getRateLimitAmount()->willReturn(2);
        $restaurant->getRateLimitRangeDuration()->willReturn(30);

        foreach ($orders as $i => $pickupExpectedAt) {
            $order = $this->createOrder(($i + 1), $restaurant->reveal(), $pickupExpectedAt);
            $orderCreated = new OrderCreated($order);

            $this->rateLimiter->handleEvent($orderCreated);
        }

        $this->assertEquals(1, $this->redis->exists('rate_limit:1'));

        // Create an order for 12:05
        $anotherOrderPickup5 = new \DateTime('2023-03-28 12:05:00');
        $anotherOrder = $this->createOrder(($i + 1), $restaurant->reveal(), $anotherOrderPickup5);

        // Check if the shift 12:05 - 12h35 is full (it should)
        $this->assertEquals(true, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));

        // Delay an order from 12:05 - 12h35 shift to the next shift
        $anotherOrderPickup36 = new \DateTime('2023-03-28 12:36:00');
        $order = $this->createOrder(1, $restaurant->reveal(), $anotherOrderPickup36);
        $this->rateLimiter->handleEvent(new OrderDelayed($order, 1860));

        // Check if the shift 12:05 - 12h35 is full (should not)
        $this->assertEquals(false, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));

        // Check if the shift 12:35 - 13h05 if full (it should)
        $this->assertEquals(true, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup36));
    }

    public function testHandleOrderDelayedEventShouldExceedLimit()
    {
        $this->markTestSkipped();

        $orders = [
            new \DateTime('2023-03-28 12:05:00'),
            new \DateTime('2023-03-28 12:05:00'),
            new \DateTime('2023-03-28 12:37:00'),
            new \DateTime('2023-03-28 12:38:00'),
        ];

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getId()->willReturn(1);

        // We want a max 10 orders per 30 minutes
        $restaurant->getRateLimitAmount()->willReturn(2);
        $restaurant->getRateLimitRangeDuration()->willReturn(30);

        foreach ($orders as $i => $pickupExpectedAt) {
            $order = $this->createOrder(($i + 1), $restaurant->reveal(), $pickupExpectedAt);
            $orderCreated = new OrderCreated($order);

            $this->rateLimiter->handleEvent($orderCreated);
        }

        $this->assertEquals(1, $this->redis->exists('rate_limit:1'));

        // Create an order for 12:05
        $anotherOrderPickup5 = new \DateTime('2023-03-28 12:05:00');
        $anotherOrder = $this->createOrder(($i + 1), $restaurant->reveal(), $anotherOrderPickup5);

        // Check if the shift 12:05 - 12h35 is full (it should)
        $this->assertEquals(true, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));

        // Delay an order from 12:05 - 12h35 shift to the next shift
        $anotherOrderPickup36 = new \DateTime('2023-03-28 12:36:00');
        $order = $this->createOrder(1, $restaurant->reveal(), $anotherOrderPickup36);

        // Check if the shift 12:35 - 13h05 if full (it should)
        $this->assertEquals(true, $this->rateLimiter->isRangeFull($order, $anotherOrderPickup36));

        // Event if the shift 12:35 - 13h05 if full, it should be accepted
        $this->rateLimiter->handleEvent(new OrderDelayed($order, 1860));

        // Check if the shift 12:05 - 12h35 is full (should not)
        $this->assertEquals(false, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));

        // Check if the shift 12:35 - 13h05 if full (it should)
        $this->assertEquals(true, $this->rateLimiter->isRangeFull($order, $anotherOrderPickup36));
    }
    public function testHandleOrderCancelledEvent()
    {

        $orders = [
            new \DateTime('2023-03-28 12:05:00'),
            new \DateTime('2023-03-28 12:05:00'),
        ];

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getId()->willReturn(1);

        // We want a max 10 orders per 30 minutes
        $restaurant->getRateLimitAmount()->willReturn(2);
        $restaurant->getRateLimitRangeDuration()->willReturn(30);

        foreach ($orders as $i => $pickupExpectedAt) {
            $order = $this->createOrder(($i + 1), $restaurant->reveal(), $pickupExpectedAt);
            $orderCreated = new OrderCreated($order);

            $this->rateLimiter->handleEvent($orderCreated);
        }

        $this->assertEquals(1, $this->redis->exists('rate_limit:1'));

        // Create an order for 12:05
        $anotherOrderPickup5 = new \DateTime('2023-03-28 12:05:00');
        $anotherOrder = $this->createOrder(($i + 1), $restaurant->reveal(), $anotherOrderPickup5);

        // Check if the shift 12:05 - 12h35 is full (it should)
        $this->assertEquals(true, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));

        $order = $this->createOrder(1, $restaurant->reveal(), $anotherOrderPickup5);
        $this->rateLimiter->handleEvent(new OrderCancelled($order));

        // Check if the shift 12:05 - 12h35 is full (it should)
        $this->assertEquals(false, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));
    }

    public function testHandleOrderPickedEvent()
    {

        $orders = [
            new \DateTime('2023-03-28 12:05:00'),
            new \DateTime('2023-03-28 12:05:00'),
        ];

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getId()->willReturn(1);

        // We want a max 10 orders per 30 minutes
        $restaurant->getRateLimitAmount()->willReturn(2);
        $restaurant->getRateLimitRangeDuration()->willReturn(30);

        foreach ($orders as $i => $pickupExpectedAt) {
            $order = $this->createOrder(($i + 1), $restaurant->reveal(), $pickupExpectedAt);
            $orderCreated = new OrderCreated($order);

            $this->rateLimiter->handleEvent($orderCreated);
        }

        $this->assertEquals(1, $this->redis->exists('rate_limit:1'));

        // Create an order for 12:05
        $anotherOrderPickup5 = new \DateTime('2023-03-28 12:05:00');
        $anotherOrder = $this->createOrder(($i + 1), $restaurant->reveal(), $anotherOrderPickup5);

        // Check if the shift 12:05 - 12h35 is full (it should)
        $this->assertEquals(true, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));

        $order = $this->createOrder(1, $restaurant->reveal(), $anotherOrderPickup5);
        $this->rateLimiter->handleEvent(new OrderPicked($order));

        // Check if the shift 12:05 - 12h35 is full (it should)
        $this->assertEquals(false, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));
    }
    public function testHandleOrderRefusedEvent()
    {

        $orders = [
            new \DateTime('2023-03-28 12:05:00'),
            new \DateTime('2023-03-28 12:05:00'),
        ];

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getId()->willReturn(1);

        // We want a max 10 orders per 30 minutes
        $restaurant->getRateLimitAmount()->willReturn(2);
        $restaurant->getRateLimitRangeDuration()->willReturn(30);

        foreach ($orders as $i => $pickupExpectedAt) {
            $order = $this->createOrder(($i + 1), $restaurant->reveal(), $pickupExpectedAt);
            $orderCreated = new OrderCreated($order);

            $this->rateLimiter->handleEvent($orderCreated);
        }

        $this->assertEquals(1, $this->redis->exists('rate_limit:1'));

        // Create an order for 12:05
        $anotherOrderPickup5 = new \DateTime('2023-03-28 12:05:00');
        $anotherOrder = $this->createOrder(($i + 1), $restaurant->reveal(), $anotherOrderPickup5);

        // Check if the shift 12:05 - 12h35 is full (it should)
        $this->assertEquals(true, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));

        $order = $this->createOrder(1, $restaurant->reveal(), $anotherOrderPickup5);
        $this->rateLimiter->handleEvent(new OrderRefused($order));

        // Check if the shift 12:05 - 12h35 is full (it should)
        $this->assertEquals(false, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup5));
    }
}
