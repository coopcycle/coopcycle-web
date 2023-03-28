<?php

namespace AppBundle\Utils;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Utils\OrdersRateLimit;
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

    private function createOrder(int $id, LocalBusiness $restaurant, \DateTime $pickupExpectedAt)
    {
    	$timeline = $this->prophesize(OrderTimeline::class);
    	$timeline->getPickupExpectedAt()->willReturn($pickupExpectedAt);

    	$order = $this->prophesize(Order::class);
    	$order->getId()->willReturn($id);
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
    		new \DateTime('2023-03-28 12:10:00'),
    	];

    	$restaurant = $this->prophesize(LocalBusiness::class);
    	$restaurant->getId()->willReturn(1);
    	$restaurant->getMaxOrdersAmount()->willReturn(10);
    	$restaurant->getMaxOrdersRangeDuration()->willReturn(30);

    	foreach ($orders as $i => $pickupExpectedAt) {

	    	$order = $this->createOrder(($i + 1), $restaurant->reveal(), $pickupExpectedAt);
	    	$orderCreated = new OrderCreated($order);

	    	$this->rateLimiter->handleEvent($orderCreated);
    	}

    	$this->assertEquals(1, $this->redis->exists('rate_limit:1'));

    	$anotherOrderPickup = new \DateTime('2023-03-28 12:06:00');
    	$anotherOrder = $this->createOrder(99, $restaurant->reveal(), $anotherOrderPickup);

    	$this->assertEquals(true, $this->rateLimiter->isRangeFull($anotherOrder, $anotherOrderPickup));
    }
}
