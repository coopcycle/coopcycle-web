<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Domain\Order\Reactor\CreateTasks;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class CreateTasksTest extends TestCase
{
    private $createTasks;

    public function setUp()
    {
        $this->routing = $this->prophesize(RoutingInterface::class);

        $this->createTasks = new CreateTasks(
            $this->routing->reveal()
        );
    }

    public function testDoesNothing()
    {
        $delivery = new Delivery();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getDelivery()
            ->willReturn($delivery);

        $order
            ->setDelivery(Argument::type(Delivery::class))
            ->shouldNotBeCalled();

        call_user_func_array($this->createTasks, [ new OrderAccepted($order->reveal()) ]);
    }

    public function testCreatesTasks()
    {
        $restaurantAddressCoords = new GeoCoordinates();
        $shippingAddressCoords = new GeoCoordinates();

        $restaurantAddress = new Address();
        $restaurantAddress->setGeo($restaurantAddressCoords);

        $shippingAddress = new Address();
        $shippingAddress->setGeo($shippingAddressCoords);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);

        $shippedAt = new \DateTime();

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getDelivery()
            ->willReturn(null);
        $order
            ->getShippedAt()
            ->willReturn($shippedAt);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);

        $this->routing
            ->getDuration($restaurantAddressCoords, $shippingAddressCoords)
            ->willReturn(60 * 15); // 15 minutes

        $order
            ->setDelivery(Argument::that(function (Delivery $delivery) use ($restaurantAddress, $shippingAddress, $shippedAt) {

                $pickup = $delivery->getPickup();
                $dropoff = $delivery->getDropoff();

                $this->assertSame($restaurantAddress, $pickup->getAddress());
                $this->assertSame($shippingAddress, $dropoff->getAddress());

                $pickupDoneBefore = clone $shippedAt;
                $pickupDoneBefore->modify('-15 minutes');

                $this->assertEquals($pickupDoneBefore, $pickup->getDoneBefore());
                $this->assertEquals($shippedAt, $dropoff->getDoneBefore());

                return true;
            }))
            ->shouldBeCalled();

        call_user_func_array($this->createTasks, [ new OrderAccepted($order->reveal()) ]);
    }
}
