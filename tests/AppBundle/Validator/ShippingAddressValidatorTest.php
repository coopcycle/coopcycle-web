<?php

namespace AppBundle\Validator;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Vendor;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\PriceFormatter;
use AppBundle\Utils\ValidationUtils;
use AppBundle\Validator\Constraints\ShippingAddress as ShippingAddressConstraint;
use AppBundle\Validator\Constraints\ShippingAddressValidator;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ShippingAddressValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    protected $routing;

    public function setUp(): void
    {
        $this->routing = $this->prophesize(RoutingInterface::class);

        parent::setUp();
    }

    protected function createValidator()
    {
        return new ShippingAddressValidator(
            $this->routing->reveal(),
            new ExpressionLanguage()
        );
    }

    private function prophesizeGetRawResponse(GeoCoordinates $origin, GeoCoordinates $destination, $distance, $duration)
    {
        $this->routing
            ->getDistance($origin, $destination)
            ->willReturn($distance);

        $this->routing
            ->getDuration($origin, $destination)
            ->willReturn($duration);
    }

    private function createAddressProphecy(GeoCoordinates $coords)
    {
        $address = $this->prophesize(Address::class);

        $address
            ->getStreetAddress()
            ->willReturn('1, Rue de Rovoli, Paris, France');

        $address
            ->getGeo()
            ->willReturn($coords);

        return $address;
    }

    private function createAddress(GeoCoordinates $coords)
    {
        $address = new Address();

        $address
            ->setStreetAddress('1, Rue de Rovoli, Paris, France');

        $address
            ->setGeo($coords);

        return $address;
    }

    private function createRestaurantProphecy(
        Address $address,
        $minimumCartAmount,
        $maxDistanceExpression,
        $canDeliver)
    {
        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getAddress()
            ->willReturn($address);

        $fulfillmentMethod = new FulfillmentMethod();
        $fulfillmentMethod->setMinimumAmount($minimumCartAmount);

        $restaurant
            ->getDeliveryPerimeterExpression()
            ->willReturn($maxDistanceExpression);
        $restaurant
            ->canDeliverAddress(Argument::any(), Argument::any(), Argument::any())
            ->willReturn($canDeliver);
        $restaurant
            ->getOpeningHoursBehavior()
            ->willReturn('asap');
        $restaurant
            ->getFulfillmentMethod(Argument::type('string'))
            ->willReturn($fulfillmentMethod);

        return $restaurant;
    }

    private function createOrderProphecy(LocalBusiness $restaurant, ?Address $shippingAddress, $takeaway = false)
    {
        $order = $this->prophesize(Order::class);

        $order
            ->getId()
            ->willReturn(null);

        $order
            ->getRestaurant()
            ->willReturn($restaurant);

        $order
            ->getVendor()
            ->willReturn(Vendor::withRestaurant($restaurant));

        $order
            ->getPickupAddress()
            ->willReturn($restaurant->getAddress());

        $order
            ->hasVendor()
            ->willReturn(true);

        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);

        $order
            ->isTakeaway()
            ->willReturn($takeaway);

        $order
            ->getFulfillmentMethod()
            ->willReturn($takeaway ? 'collection' : 'delivery');

        return $order;
    }

    public function testDistanceValidation()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddress($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = false
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress
        );

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('+1 hour'), 5);

        $order
            ->getShippingTimeRange()
            ->willReturn($shippingTimeRange);
        $order
            ->getItemsTotal()
            ->willReturn(2500);
        $order
            ->containsDisabledProduct()
            ->willReturn(false);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $distance = 3500,
            $duration = 300
        );

        $this->setObject($order->reveal());

        $constraint = new ShippingAddressConstraint();
        $violations = $this->validator->validate($shippingAddress, $constraint);

        $this->buildViolation($constraint->addressTooFarMessage)
            ->atPath('property.path')
            ->setCode(ShippingAddressConstraint::ADDRESS_TOO_FAR)
            ->assertRaised();
    }

    public function testOrderWithMissingShippingAddress()
    {
        $restaurantAddressCoords = new GeoCoordinates();
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            null
        );

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('+1 hour'), 5);

        $order
            ->getShippingTimeRange()
            ->willReturn($shippingTimeRange);
        $order
            ->getItemsTotal()
            ->willReturn(2500);
        $order
            ->containsDisabledProduct()
            ->willReturn(false);

        $this->setObject($order->reveal());

        $constraint = new ShippingAddressConstraint();
        $violations = $this->validator->validate(null, $constraint);

        $this->buildViolation($constraint->addressNotSetMessage)
            ->atPath('property.path')
            ->setCode(ShippingAddressConstraint::ADDRESS_NOT_SET)
            ->assertRaised();
    }

    public function testTakeawayOrderWithMissingShippingAddressIsValid()
    {
        $restaurantAddressCoords = new GeoCoordinates();
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            null,
            true
        );

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('+1 hour'), 5);

        $order
            ->getShippingTimeRange()
            ->willReturn($shippingTimeRange);
        $order
            ->getItemsTotal()
            ->willReturn(2500);
        $order
            ->containsDisabledProduct()
            ->willReturn(false);

        $this->setObject($order->reveal());

        $constraint = new ShippingAddressConstraint();
        $violations = $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testOrderWithItemsTotalZeroIsValid()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddress($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress
        );

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('+1 hour'), 5);

        $order
            ->getShippingTimeRange()
            ->willReturn($shippingTimeRange);
        $order
            ->getItemsTotal()
            ->willReturn(0);
        $order
            ->containsDisabledProduct()
            ->willReturn(false);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $maxDistanceExpression = 'distance < 1500',
            $duration = 300
        );

        $this->setObject($order->reveal());

        $constraint = new ShippingAddressConstraint();
        $violations = $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testOrderIsValid()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddress($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress
        );

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('+1 hour'), 5);

        $order
            ->getShippingTimeRange()
            ->willReturn($shippingTimeRange);
        $order
            ->getItemsTotal()
            ->willReturn(2500);
        $order
            ->containsDisabledProduct()
            ->willReturn(false);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $maxDistanceExpression = 'distance < 1500',
            $duration = 300
        );

        $this->setObject($order->reveal());

        $constraint = new ShippingAddressConstraint();
        $violations = $this->validator->validate($shippingAddress, $constraint);

        $this->assertNoViolation();
    }
}
