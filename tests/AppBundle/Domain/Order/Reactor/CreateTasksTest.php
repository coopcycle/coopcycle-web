<?php

namespace Tests\AppBundle\MessageHandler\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\MessageHandler\Order\CreateTasks;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\DeliveryManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTextEncoder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;

class CreateTasksTest extends TestCase
{
    use ProphecyTrait;

    private $createTasks;
    private $deliveryManager;
    private $orderTextEncoder;

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
        $order
            ->getTotal()
            ->willReturn(100);

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

        $this->assertEquals('AB123', $delivery->getPickup()->getMetadata()['order_number']);
        $this->assertEquals('AB123', $delivery->getDropoff()->getMetadata()['order_number']);

        $this->assertEquals('CARD', $delivery->getPickup()->getMetadata()['payment_method']);
        $this->assertEquals('CARD', $delivery->getDropoff()->getMetadata()['payment_method']);
    }
}
