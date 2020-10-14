<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\Sylius\OrderTarget;
use AppBundle\Service\RoutingInterface;
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
            [ 3950, '1 hours 5 minutes' ],
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

        $target = OrderTarget::withRestaurant($restaurant);

        $this->routing
            ->getDuration(
                Argument::type(GeoCoordinates::class),
                Argument::type(GeoCoordinates::class)
            )
            ->willReturn($seconds);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getTarget()
            ->willReturn($target);
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

        $target = OrderTarget::withRestaurant($restaurant);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getTarget()
            ->willReturn($target);
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
