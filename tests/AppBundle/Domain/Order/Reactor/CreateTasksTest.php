<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\DataType\TsRange;
use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Domain\Order\Reactor\CreateTasks;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Vendor;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTextEncoder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;

class CreateTasksTest extends TestCase
{
    use ProphecyTrait;

    private $createTasks;

    public function setUp(): void
    {
        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->orderTextEncoder = $this->prophesize(OrderTextEncoder::class);

        $this->orderTextEncoder
            ->encode(Argument::type(OrderInterface::class), 'txt')
            ->willReturn('Order XXX');

        $this->createTasks = new CreateTasks(
            $this->routing->reveal(),
            $this->orderTextEncoder->reveal()
        );
    }

    public function testDoesNothingWhenDeliveryAlreadyExists()
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

    public function testDoesNothingWhenOrderIsTakeaway()
    {
        $delivery = new Delivery();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getDelivery()
            ->willReturn(null);
        $order
            ->isTakeaway()
            ->willReturn(true);

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

        $shippingTimeRangeLower = new \DateTime('2020-04-08 20:00:00');
        $shippingTimeRangeUpper = new \DateTime('2020-04-08 20:10:00');

        $shippingTimeRange = new TsRange();
        $shippingTimeRange->setLower($shippingTimeRangeLower);
        $shippingTimeRange->setUpper($shippingTimeRangeUpper);

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getDelivery()
            ->willReturn(null);
        $order
            ->isTakeaway()
            ->willReturn(false);
        $order
            ->getShippingTimeRange()
            ->willReturn($shippingTimeRange);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getVendor()
            ->willReturn(Vendor::withRestaurant($restaurant));
        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);

        $this->routing
            ->getDuration($restaurantAddressCoords, $shippingAddressCoords)
            ->willReturn(60 * 15); // 15 minutes

        $order
            ->setDelivery(Argument::that(function (Delivery $delivery) use (
                $restaurantAddress, $shippingAddress, $shippingTimeRangeLower, $shippingTimeRangeUpper) {

                $pickup = $delivery->getPickup();
                $dropoff = $delivery->getDropoff();

                $this->assertSame($restaurantAddress, $pickup->getAddress());
                $this->assertSame($shippingAddress, $dropoff->getAddress());

                // Dropoff average = 20:05
                // Pickup average  = 19:50 (20:05 - 15 minutes)
                $this->assertEquals(new \DateTime('2020-04-08 19:45:00'), $pickup->getAfter());
                $this->assertEquals(new \DateTime('2020-04-08 19:55:00'), $pickup->getBefore());

                $this->assertEquals($shippingTimeRangeLower, $dropoff->getAfter());
                $this->assertEquals($shippingTimeRangeUpper, $dropoff->getBefore());

                $this->assertEquals('Order XXX', $pickup->getComments());
                $this->assertEquals('Order XXX', $dropoff->getComments());

                return true;
            }))
            ->shouldBeCalled();

        call_user_func_array($this->createTasks, [ new OrderAccepted($order->reveal()) ]);
    }
}
