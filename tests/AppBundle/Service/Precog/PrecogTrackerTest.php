<?php

namespace Tests\AppBundle\Service\Precog;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Sylius\Order;
use AppBundle\DataType\TsRange;
use AppBundle\Entity\Vendor;
use AppBundle\Service\LiveUpdates;
use AppBundle\Service\Precog\PrecogStage;
use AppBundle\Service\Precog\PrecogTracker;
use AppBundle\Sylius\Customer\CustomerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Redis;

class PrecogTrackerTest extends TestCase
{
    use ProphecyTrait;

    private $redis;
    private $liveUpdates;

    public function setUp(): void
    {
        $this->redis = $this->prophesize(Redis::class);
        $this->liveUpdates = $this->prophesize(LiveUpdates::class);

        // A fresh cart with nothing stored against it yet.
        $this->redis->get(Argument::any())->willReturn(false);
        $this->redis->setex(Argument::cetera())->willReturn(true);
        $this->redis->zAdd(Argument::cetera())->willReturn(1);
    }

    private function tracker(bool $enabled = true): PrecogTracker
    {
        return new PrecogTracker(
            $this->redis->reveal(),
            $this->liveUpdates->reveal(),
            'a-secret',
            $enabled
        );
    }

    private function cart(float $latitude = 48.856613, float $longitude = 2.352222, bool $complete = true): Order
    {
        $address = new Address();
        $address->setGeo(new GeoCoordinates($latitude, $longitude));

        $order = $this->getMockBuilder(Order::class)
            ->onlyMethods(['getId', 'hasVendor', 'getVendor'])
            ->getMock();

        $order->method('getId')->willReturn(42);
        $order->method('hasVendor')->willReturn(true);

        $vendor = $this->prophesize(Vendor::class);
        $vendor->getName()->willReturn('Chez Max');
        $order->method('getVendor')->willReturn($vendor->reveal());

        $order->setShippingAddress($address);

        if ($complete) {
            $order->setShippingTimeRange(TsRange::create(
                new \DateTime('2026-11-10 19:00:00'),
                new \DateTime('2026-11-10 19:30:00')
            ));
            $order->setCustomer($this->prophesize(CustomerInterface::class)->reveal());
        }

        return $order;
    }

    public function testDisabledTrackerDoesNothing()
    {
        $this->redis->setex(Argument::cetera())->shouldNotBeCalled();

        $this->assertNull($this->tracker(false)->ping($this->cart(), PrecogTracker::PAGE_CHECKOUT));
    }

    public function testCartPageIsTheFaintestRung()
    {
        $stage = $this->tracker()->ping($this->cart(), PrecogTracker::PAGE_CART);

        $this->assertSame(PrecogStage::CartAddress, $stage);
        $this->assertSame(1, $stage->rank());
    }

    public function testCheckoutIsOnlyReadyWhenTheCartCouldBePaidFor()
    {
        $incomplete = $this->cart(complete: false);

        $this->assertSame(
            PrecogStage::CheckoutOpen,
            $this->tracker()->ping($incomplete, PrecogTracker::PAGE_CHECKOUT)
        );
    }

    public function testPaymentPageOutranksCheckout()
    {
        $open = $this->tracker()->ping($this->cart(), PrecogTracker::PAGE_PAYMENT);
        $submitted = $this->tracker()->ping($this->cart(), PrecogTracker::PAGE_PAYMENT, true);

        $this->assertSame(PrecogStage::PaymentOpen, $open);
        $this->assertSame(PrecogStage::PaymentSubmitted, $submitted);
        $this->assertGreaterThan($open->rank(), $submitted->rank());
    }

    public function testAPageTheServerDoesNotKnowGetsNoGhost()
    {
        $this->assertNull($this->tracker()->ping($this->cart(), 'confirmation'));
    }

    public function testACartWithoutAnAddressGetsNoGhost()
    {
        $order = $this->getMockBuilder(Order::class)
            ->onlyMethods(['getId', 'hasVendor'])
            ->getMock();
        $order->method('getId')->willReturn(42);
        $order->method('hasVendor')->willReturn(true);

        $this->assertNull($this->tracker()->ping($order, PrecogTracker::PAGE_CHECKOUT));
    }

    /**
     * The whole privacy argument rests on this: a dispatcher watching a cart at
     * the menu stage must not be able to read a street off the map.
     */
    public function testThePublishedPositionIsCoarsenedToTheStagePrecision()
    {
        $published = null;

        $this->liveUpdates
            ->toDispatchers('precog:updated', Argument::any())
            ->will(function ($args) use (&$published) {
                $published = $args[1]['precog'];
            });

        $this->tracker()->ping($this->cart(48.856613, 2.352222), PrecogTracker::PAGE_CART);

        $this->assertNotNull($published);
        $this->assertSame(1200, $published['precision']);

        // 1200 m is about 0.0108 degrees of latitude; the point must have moved
        // onto that grid rather than staying where the customer actually lives.
        $this->assertNotSame(48.856613, $published['latitude']);
        $this->assertLessThan(0.011, abs($published['latitude'] - 48.856613));
        $this->assertLessThan(0.017, abs($published['longitude'] - 2.352222));
    }

    public function testCoarseningIsStableBetweenHeartbeats()
    {
        $positions = [];

        $this->liveUpdates
            ->toDispatchers('precog:updated', Argument::any())
            ->will(function ($args) use (&$positions) {
                $positions[] = [$args[1]['precog']['latitude'], $args[1]['precog']['longitude']];
            });

        $tracker = $this->tracker();
        $tracker->ping($this->cart(), PrecogTracker::PAGE_CART);
        $tracker->ping($this->cart(), PrecogTracker::PAGE_CART);

        // A dot that wandered between beats would read as a moving courier.
        $this->assertCount(2, $positions);
        $this->assertSame($positions[0], $positions[1]);
    }

    public function testEachRungPublishesAFinerPositionThanTheOneBelow()
    {
        $precisions = array_map(
            fn (PrecogStage $stage) => $stage->precisionInMeters(),
            PrecogStage::cases()
        );

        for ($i = 1; $i < count($precisions); $i++) {
            $this->assertLessThan($precisions[$i - 1], $precisions[$i]);
        }
    }

    /**
     * Publishing on every heartbeat would put more traffic on the dispatch
     * channel than the actual deliveries do.
     */
    public function testAHeartbeatThatChangesNothingIsNotPublished()
    {
        $this->redis->get(Argument::any())->willReturn(json_encode(['stage' => 'payment.open']));

        $this->liveUpdates->toDispatchers(Argument::cetera())->shouldNotBeCalled();

        $this->tracker()->ping($this->cart(), PrecogTracker::PAGE_PAYMENT);
    }

    public function testMovingUpARungIsPublished()
    {
        $this->redis->get(Argument::any())->willReturn(json_encode(['stage' => 'checkout.ready']));

        $this->liveUpdates->toDispatchers('precog:updated', Argument::any())->shouldBeCalledOnce();

        $this->tracker()->ping($this->cart(), PrecogTracker::PAGE_PAYMENT);
    }

    public function testForgettingACartTellsTheBoardAndClearsRedis()
    {
        $tracker = $this->tracker();
        $id = $tracker->idFor(42);

        $this->redis->del('precog:item:' . $id)->shouldBeCalled()->willReturn(1);
        $this->redis->zRem('precog:index', $id)->shouldBeCalled()->willReturn(1);
        $this->liveUpdates
            ->toDispatchers('precog:gone', ['precog' => ['id' => $id]])
            ->shouldBeCalledOnce();

        $tracker->forget(42);
    }

    public function testTheGhostIdDoesNotLeakTheCartId()
    {
        $id = $this->tracker()->idFor(42);

        $this->assertSame(16, strlen($id));
        $this->assertStringNotContainsString('42', $id);
        $this->assertSame($id, $this->tracker()->idFor(42));
        $this->assertNotSame($id, $this->tracker()->idFor(43));
    }

    public function testListingPrunesExpiredEntriesFromTheIndex()
    {
        $this->redis->zRemRangeByScore('precog:index', '-inf', Argument::type('string'))
            ->shouldBeCalled()
            ->willReturn(1);
        $this->redis->zRange('precog:index', 0, -1)->willReturn(['abc']);
        $this->redis->mGet(['precog:item:abc'])->willReturn([json_encode(['id' => 'abc'])]);

        $this->assertSame([['id' => 'abc']], $this->tracker()->all());
    }

    public function testListingSkipsEntriesRedisHasAlreadyExpired()
    {
        $this->redis->zRemRangeByScore(Argument::cetera())->willReturn(0);
        $this->redis->zRange('precog:index', 0, -1)->willReturn(['abc', 'def']);
        $this->redis->mGet(Argument::any())->willReturn([false, json_encode(['id' => 'def'])]);

        $this->assertSame([['id' => 'def']], $this->tracker()->all());
    }
}
