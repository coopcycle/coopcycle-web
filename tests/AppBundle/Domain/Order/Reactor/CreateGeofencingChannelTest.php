<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Order\Reactor\CreateGeofencingChannel;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Service\Geofencing;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;
use Psr\Log\NullLogger;

class CreateGeofencingChannelTest extends TestCase
{
    use ProphecyTrait;

    private $reactor;

    public function setUp(): void
    {
        $this->geofencing = $this->prophesize(Geofencing::class);

        $this->reactor = new CreateGeofencingChannel(
            $this->geofencing->reveal()
        );
    }

    public function testCreatesChannel()
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

        $this->geofencing
            ->createChannel($dropoff->reveal())
            ->shouldBeCalled();

        call_user_func_array($this->reactor, [ new OrderPicked($order->reveal()) ]);
    }
}
