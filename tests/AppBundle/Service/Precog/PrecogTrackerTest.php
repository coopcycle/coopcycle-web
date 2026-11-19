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
     * Collects the position published at every rung, for one address.
     *
     * @return array<string, array{0: float, 1: float, 2: int}> lat, lng, radius
     */
    private function ladderFor(float $latitude, float $longitude): array
    {
        $rungs = [];

        $this->liveUpdates
            ->toDispatchers('precog:updated', Argument::any())
            ->will(function ($args) use (&$rungs) {
                $g = $args[1]['precog'];
                $rungs[$g['stage']] = [$g['latitude'], $g['longitude'], $g['precision']];
            });

        // An incomplete cart is the only way to reach checkout.open, since a
        // cart that could be paid for reports checkout.ready instead.
        foreach ([
            [PrecogTracker::PAGE_CART, false, true],
            [PrecogTracker::PAGE_CHECKOUT, false, false],
            [PrecogTracker::PAGE_CHECKOUT, false, true],
            [PrecogTracker::PAGE_PAYMENT, false, true],
            [PrecogTracker::PAGE_PAYMENT, true, true],
        ] as [$page, $submitted, $complete]) {
            // Each rung needs its own tracker: one tracker would see the stored
            // stage from the previous call and stay quiet, publishing nothing.
            $this->tracker()->ping(
                $this->cart($latitude, $longitude, $complete),
                $page,
                $submitted
            );
        }

        return $rungs;
    }

    private static function metresBetween(array $a, array $b): float
    {
        $r = 6371000;
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $mid = deg2rad(($a[0] + $b[0]) / 2);

        return sqrt(pow($dLat * $r, 2) + pow($dLng * $r * cos($mid), 2));
    }

    public static function addressProvider(): array
    {
        return [
            'Paris'     => [48.8586508, 2.3470741],
            'Paris 14e' => [48.8339, 2.3324],
            'Lyon'      => [45.7640, 4.8357],
            'Brussels'  => [50.8503, 4.3517],
            'Barcelona' => [41.3874, 2.1686],
            'Stockholm' => [59.3293, 18.0686],
        ];
    }

    /**
     * The whole privacy argument rests on this: a dispatcher watching a cart at
     * the menu stage must not be able to read a street off the map.
     */
    public function testThePublishedPositionIsCoarsenedToTheStagePrecision()
    {
        $rungs = $this->ladderFor(48.8586508, 2.3470741);

        $this->assertSame(1200, $rungs['cart.address'][2]);

        // Far enough off that the street is not readable, which is the point.
        $this->assertGreaterThan(
            100,
            self::metresBetween([48.8586508, 2.3470741], $rungs['cart.address'])
        );
    }

    /**
     * The board draws a disc of the published radius and claims the delivery is
     * somewhere inside it. If that is ever false the whole layer is a lie.
     *
     * @dataProvider addressProvider
     */
    public function testTheAddressAlwaysLiesInsideTheDiscTheBoardDraws(float $latitude, float $longitude)
    {
        foreach ($this->ladderFor($latitude, $longitude) as $stage => $rung) {
            $this->assertLessThanOrEqual(
                $rung[2],
                self::metresBetween([$latitude, $longitude], $rung),
                sprintf('%s put the address outside its own disc', $stage)
            );
        }
    }

    /**
     * Moving up a rung has to move the dot *inward*. The first version rounded
     * to a grid sized per rung, and because those grids did not nest the dot
     * could jump the better part of a kilometre sideways on a stage change --
     * which on a city map reads as the order having moved somewhere else
     * entirely, not as the forecast getting sharper.
     *
     * @dataProvider addressProvider
     */
    public function testClimbingARungNeverThrowsTheDotOutsideTheDiscAlreadyDrawn(float $latitude, float $longitude)
    {
        $previous = null;

        foreach ($this->ladderFor($latitude, $longitude) as $stage => $rung) {
            if (null !== $previous) {
                $this->assertLessThanOrEqual(
                    $previous[2],
                    self::metresBetween($previous, $rung),
                    sprintf('%s moved the dot outside the previous disc', $stage)
                );
            }

            $previous = $rung;
        }
    }

    /**
     * Each rung's cell has to sit wholly inside the one above it. Otherwise a
     * dispatcher watching one cart climb the funnel could intersect the cells
     * from two rungs and get a tighter fix than the finest rung ever promised --
     * the positions would leak more together than any of them does alone.
     *
     * Checked here as the halving of the radius, which is what the nesting rests
     * on: PrecogTracker sizes the grid from this number and floors onto it, so
     * cells nest exactly when the radii do.
     */
    public function testEachRungIsExactlyHalfTheOneAbove()
    {
        $radii = array_map(
            fn (PrecogStage $stage) => $stage->precisionInMeters(),
            PrecogStage::cases()
        );

        for ($i = 1; $i < count($radii); $i++) {
            $this->assertEqualsWithDelta(
                $radii[$i - 1] / 2,
                $radii[$i],
                0.0,
                'Radii must halve, or the grids they size do not nest'
            );
        }
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

    /**
     * The real nesting property, stated the way an attacker would use it.
     *
     * Two addresses that the finest rung cannot tell apart must also be
     * indistinguishable at every coarser rung. If a coarse rung ever splits
     * them, its cells are not unions of the fine ones -- and a dispatcher
     * watching one cart climb the funnel could intersect the cells it published
     * at two rungs and pin the address down more tightly than the finest rung
     * ever promised.
     *
     * Rounding to the nearest grid node, which is what this did first, fails
     * exactly here: the coarse grid's nodes sit in the middle of the fine
     * grid's cells, so the two interleave instead of nesting.
     */
    public function testAddressesTheFinestRungCannotSeparateStayTogetherAtEveryCoarserRung()
    {
        $byFinestCell = [];

        // A patch a few hundred metres across, finely sampled, so that plenty of
        // pairs share a rank-5 cell while straddling coarser boundaries.
        for ($i = 0; $i < 160; $i++) {
            $latitude  = 48.8586508 + ($i % 16) * 0.0004;
            $longitude = 2.3470741 + intdiv($i, 16) * 0.0006;

            $rungs = $this->ladderFor($latitude, $longitude);

            $finest = implode(',', array_slice($rungs['payment.submitted'], 0, 2));

            $coarser = [];
            foreach (['cart.address', 'checkout.open', 'checkout.ready', 'payment.open'] as $stage) {
                $coarser[$stage] = implode(',', array_slice($rungs[$stage], 0, 2));
            }

            $byFinestCell[$finest][] = $coarser;
        }

        $shared = 0;

        foreach ($byFinestCell as $finest => $observations) {
            if (count($observations) < 2) {
                continue;
            }

            $shared++;
            $first = array_shift($observations);

            foreach ($observations as $other) {
                $this->assertSame(
                    $first,
                    $other,
                    sprintf('Two addresses sharing the finest cell %s were separated by a coarser rung', $finest)
                );
            }
        }

        // Guard against the test passing because nothing actually collided.
        $this->assertGreaterThan(10, $shared, 'Sample produced too few shared cells to prove anything');
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

    /**
     * The board cannot tell a sale from an abandoned cart otherwise: both end as
     * a halo that is no longer there. The reason is what lets it mark one and
     * stay quiet about the other.
     */
    public function testForgettingACartTellsTheBoardWhyAndClearsRedis()
    {
        $tracker = $this->tracker();
        $id = $tracker->idFor(42);

        $this->redis->del('precog:item:' . $id)->shouldBeCalled()->willReturn(1);
        $this->redis->zRem('precog:index', $id)->shouldBeCalled()->willReturn(1);
        $this->liveUpdates
            ->toDispatchers('precog:gone', ['precog' => [
                'id' => $id,
                'reason' => PrecogTracker::REASON_ORDER_PLACED,
            ]])
            ->shouldBeCalledOnce();

        $tracker->forget(42);
    }

    public function testADifferentReasonIsPassedThroughUnchanged()
    {
        $tracker = $this->tracker();
        $id = $tracker->idFor(42);

        $this->redis->del(Argument::any())->willReturn(1);
        $this->redis->zRem(Argument::cetera())->willReturn(1);
        $this->liveUpdates
            ->toDispatchers('precog:gone', ['precog' => ['id' => $id, 'reason' => 'merged']])
            ->shouldBeCalledOnce();

        $tracker->forget(42, 'merged');
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
