<?php

namespace Tests\AppBundle\Service;

use AppBundle\Service\Geofencing;
use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Order\Reactor\CreateGeofencingChannel;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Redis;
use Prophecy\Argument;
use Psr\Log\NullLogger;

class GeofencingTest extends TestCase
{
    use ProphecyTrait;

    private $geofencing;

    public function setUp(): void
    {
        $this->tile38 = $this->prophesize(Redis::class);

        $this->geofencing = new Geofencing(
            $this->tile38->reveal(),
            'coopcycle',
            'fleet'
        );
    }

    public function testCreateChannel()
    {
        $dropoffAddress = new Address();
        $dropoffAddress->setGeo(new GeoCoordinates(48.856613, 2.352222));

        $dropoff = $this->prophesize(Task::class);
        $dropoff
            ->isDoorstep()
            ->willReturn(true);
        $dropoff
            ->getAddress()
            ->willReturn($dropoffAddress);
        $dropoff
            ->getId()
            ->willReturn(42);

        $delivery = $this->prophesize(Delivery::class);
        $delivery
            ->getDropoff()
            ->willReturn($dropoff->reveal());

        $order = $this->prophesize(Order::class);
        $order
            ->getDelivery()
            ->willReturn($delivery->reveal());

        $this->tile38
            ->rawCommand(
                'SETCHAN',
                'coopcycle:dropoff:42',
                'NEARBY',
                'fleet',
                'FENCE',
                'DETECT',
                'enter',
                'COMMANDS',
                'set',
                'POINT',
                48.856613,
                2.352222,
                (string) Task::GEOFENCING_RADIUS
            )
            ->shouldBeCalled();

        $this->geofencing->createChannel($dropoff->reveal());
    }

    public function testDeleteChannel()
    {
        $dropoff = $this->prophesize(Task::class);
        $dropoff
            ->isDoorstep()
            ->willReturn(true);
        $dropoff
            ->getId()
            ->willReturn(42);

        $delivery = $this->prophesize(Delivery::class);
        $delivery
            ->getDropoff()
            ->willReturn($dropoff->reveal());

        $order = $this->prophesize(Order::class);
        $order
            ->getDelivery()
            ->willReturn($delivery->reveal());

        $this->tile38
            ->rawCommand(
                'DELCHAN',
                'coopcycle:dropoff:42'
            )
            ->shouldBeCalled();

        $this->geofencing->deleteChannel($dropoff->reveal());
    }
}
