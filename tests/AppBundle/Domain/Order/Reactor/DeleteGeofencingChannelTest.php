<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Reactor\DeleteGeofencingChannel;
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

class DeleteGeofencingChannelTest extends TestCase
{
    use ProphecyTrait;

    private $reactor;

    public function setUp(): void
    {
        $this->geofencing = $this->prophesize(Geofencing::class);

        $this->reactor = new DeleteGeofencingChannel(
            $this->geofencing->reveal()
        );
    }

    public function testDeletesChannel()
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

        $this->geofencing
            ->deleteChannel($dropoff->reveal())
            ->shouldBeCalled();

        call_user_func_array($this->reactor, [ new Event\OrderDropped($order->reveal()) ]);
    }

    public function testDeletesChannelWithNewOrder()
    {
        $order = $this->prophesize(Order::class);
        $order
            ->getDelivery()
            ->willReturn(null);

        $this->geofencing
            ->deleteChannel(Argument::type(Task::class))
            ->shouldNotBeCalled();

        call_user_func_array($this->reactor, [ new Event\OrderDropped($order->reveal()) ]);
    }
}
