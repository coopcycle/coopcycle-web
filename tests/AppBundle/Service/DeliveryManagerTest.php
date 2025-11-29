<?php

namespace Tests\AppBundle\Service;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\OrderTimelineCalculator;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DeliveryManagerTest extends KernelTestCase
{
    use ProphecyTrait;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->denormalizer = $this->prophesize(DenormalizerInterface::class);
        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);
        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->orderTimelineCalculator = $this->prophesize(OrderTimelineCalculator::class);
        $this->storeExtractor = $this->prophesize(TokenStoreExtractor::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testCreateFromOrder()
    {
        $restaurantAddress = new Address();
        $restaurantAddressCoords = new GeoCoordinates();
        $restaurantAddress->setGeo($restaurantAddressCoords);

        $shippingAddress = new Address();
        $shippingAddressCoords = new GeoCoordinates();
        $shippingAddress->setGeo($shippingAddressCoords);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);

        $order = new Order();
        $order->setRestaurant($restaurant);
        // $order->addItem($this->createOrderItem(1000));
        $order->setShippingAddress($shippingAddress);

        $shippingTimeRange = new TsRange();
        $shippingTimeRange->setLower(new \DateTime('2020-04-09 19:55:00'));
        $shippingTimeRange->setUpper(new \DateTime('2020-04-09 20:05:00'));

        $this->orderTimeHelper
            ->getShippingTimeRange($order)
            ->willReturn($shippingTimeRange);

        $expectedPickupAfter = new \DateTime('2020-04-09 19:40:00');
        $expectedPickupBefore = new \DateTime('2020-04-09 19:50:00');

        $this->routing
            ->getDistance($restaurantAddressCoords, $shippingAddressCoords)
            ->willReturn(1200);

        $this->routing
            ->getDuration($restaurantAddressCoords, $shippingAddressCoords)
            ->willReturn(900);

        $timeline = new OrderTimeline();
        $timeline->setPickupExpectedAt(new \DateTime('2020-04-09 19:45:00'));

        $this->orderTimelineCalculator
            ->calculate($order, $shippingTimeRange)
            ->willReturn($timeline);

        $deliveryManager = new DeliveryManager(
            $this->denormalizer->reveal(),
            $this->routing->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->orderTimelineCalculator->reveal(),
            $this->storeExtractor->reveal(),
            $this->entityManager->reveal(),
        );

        $delivery = $deliveryManager->createFromOrder($order);

        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        $this->assertEquals(1200, $delivery->getDistance());
        $this->assertEquals($expectedPickupAfter, $pickup->getAfter());
        $this->assertEquals($expectedPickupBefore, $pickup->getBefore());
        $this->assertEquals($restaurantAddress, $pickup->getAddress());
        $this->assertEquals($shippingAddress, $dropoff->getAddress());
    }

    public function testCreateFromOrderThrowsException()
    {
        $this->expectException(ShippingAddressMissingException::class);

        $restaurantAddress = new Address();
        $restaurantAddressCoords = new GeoCoordinates();
        $restaurantAddress->setGeo($restaurantAddressCoords);

        $shippingAddress = new Address();
        $shippingAddressCoords = new GeoCoordinates();
        $shippingAddress->setGeo($shippingAddressCoords);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);

        $order = new Order();
        $order->setRestaurant($restaurant);
        // The shipping address is missing
        // $order->setShippingAddress(null);

        $deliveryManager = new DeliveryManager(
            $this->denormalizer->reveal(),
            $this->routing->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->orderTimelineCalculator->reveal(),
            $this->storeExtractor->reveal(),
            $this->entityManager->reveal(),
        );

        $delivery = $deliveryManager->createFromOrder($order);
    }

}
