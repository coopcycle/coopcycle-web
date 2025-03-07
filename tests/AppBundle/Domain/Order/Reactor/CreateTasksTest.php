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
use AppBundle\Service\DeliveryManager;
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
        $this->deliveryManager = $this->prophesize(DeliveryManager::class);
        $this->orderTextEncoder = $this->prophesize(OrderTextEncoder::class);

        $this->orderTextEncoder
            ->encode(Argument::type(OrderInterface::class), 'txt')
            ->willReturn('Order XXX');

        $this->createTasks = new CreateTasks(
            $this->deliveryManager->reveal(),
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
            ->willReturn($restaurant);
        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);
        $order
            ->getNumber()
            ->willReturn('AB123');
        $order
            ->getPaymentMethod()
            ->willReturn('CARD');
        $order
            ->getNotes()
            ->willReturn(null);

        $delivery = new Delivery();

        $this->deliveryManager
            ->createFromOrder($order->reveal())
            ->willReturn($delivery);

        $order
            ->setDelivery($delivery)
            ->shouldBeCalled();

        call_user_func_array($this->createTasks, [ new OrderAccepted($order->reveal()) ]);

        $this->assertEquals('Order XXX', $delivery->getPickup()->getComments());
        $this->assertEquals('Order XXX', $delivery->getDropoff()->getComments());
    }
}
