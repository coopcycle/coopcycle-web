<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\ShippingTimeCalculator;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class ShippingTimeCalculatorTest extends TestCase
{
    use ProphecyTrait;

    private $routing;
    private $calculator;

    public function setUp(): void
    {
        $this->routing = $this->prophesize(RoutingInterface::class);

        $this->calculator = new ShippingTimeCalculator($this->routing->reveal(), '10 minutes');
    }

    public function calculateProvider()
    {
        return [
            [ 600, '10 minutes' ],
            [ 3950, '1 hour 5 minutes 50 seconds' ],
            [ 435, '7 minutes 15 seconds' ],
            [ 1, '1 second' ],
            [ 60, '1 minute' ],
            // When the time is 0, we use the fallback
            [ 0, '10 minutes' ],
        ];
    }

    /**
     * @dataProvider calculateProvider
     */
    public function testCalculate($seconds, $expression)
    {
        $restaurantAddressCoords = new GeoCoordinates();
        $shippingAddressCoords = new GeoCoordinates();

        $restaurantAddress = new Address();
        $restaurantAddress->setGeo($restaurantAddressCoords);

        $shippingAddress = new Address();
        $shippingAddress->setGeo($shippingAddressCoords);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);

        $this->routing
            ->getDuration(
                Argument::type(GeoCoordinates::class),
                Argument::type(GeoCoordinates::class)
            )
            ->willReturn($seconds);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getPickupAddress()
            ->willReturn($restaurantAddress);
        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);

        $this->assertEquals($expression, $this->calculator->calculate($order->reveal()));
    }

    public function testCalculateReturnsFallback()
    {
        $restaurantAddressCoords = new GeoCoordinates();

        $restaurantAddress = new Address();
        $restaurantAddress->setGeo($restaurantAddressCoords);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getPickupAddress()
            ->willReturn($restaurantAddress);
        $order
            ->getShippingAddress()
            ->willReturn(null);

        $this->assertEquals('10 minutes', $this->calculator->calculate($order->reveal()));

        $shippingAddress = new Address();

        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);

        $this->assertEquals('10 minutes', $this->calculator->calculate($order->reveal()));
    }
}
