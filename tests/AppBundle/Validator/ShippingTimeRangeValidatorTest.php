<?php

namespace AppBundle\Validator;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ValidationUtils;
use AppBundle\Validator\Constraints\ShippingTimeRange as ShippingTimeRangeConstraint;
use AppBundle\Validator\Constraints\ShippingTimeRangeValidator;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ShippingTimeRangeValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    protected $shippingDateFilter;

    public function setUp(): void
    {
        $this->shippingDateFilter = $this->prophesize(ShippingDateFilter::class);
        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);

        parent::setUp();
    }

    protected function createValidator()
    {
        return new ShippingTimeRangeValidator(
            $this->shippingDateFilter->reveal(),
            $this->orderTimeHelper->reveal()
        );
    }

    private function createAddressProphecy(GeoCoordinates $coords)
    {
        $address = $this->prophesize(Address::class);

        $address
            ->getGeo()
            ->willReturn($coords);

        return $address;
    }

    private function createRestaurantProphecy(
        Address $address,
        $minimumCartAmount,
        $maxDistanceExpression,
        $canDeliver)
    {
        $restaurant = $this->prophesize(Restaurant::class);

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

    private function createOrderProphecy(Restaurant $restaurant, ?Address $shippingAddress, $takeaway = false)
    {
        $order = $this->prophesize(Order::class);

        $order
            ->getId()
            ->willReturn(null);

        $order
            ->getRestaurant()
            ->willReturn($restaurant);

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

    public function testPastDateWithUnsavedOrderValidation()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('-1 hour'), 5);

        $order = $order->reveal();

        $this->setObject($order);

        $this->shippingDateFilter
            ->accept($order, $shippingTimeRange, Argument::type(\DateTime::class))
            ->willReturn(false);

        $constraint = new ShippingTimeRangeConstraint();
        $violations = $this->validator->validate($shippingTimeRange, $constraint);

        $this->buildViolation($constraint->shippedAtExpiredMessage)
            ->setCode(ShippingTimeRangeConstraint::SHIPPED_AT_EXPIRED)
            ->assertRaised();
    }

    public function testPastDateWithNewSavedOrderValidation()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getState()
            ->willReturn(Order::STATE_CART);

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('-1 hour'), 5);

        $order = $order->reveal();

        $this->setObject($order);

        $this->shippingDateFilter
            ->accept($order, $shippingTimeRange, Argument::type(\DateTime::class))
            ->willReturn(false);

        $constraint = new ShippingTimeRangeConstraint();
        $violations = $this->validator->validate($shippingTimeRange, $constraint);

        $this->buildViolation($constraint->shippedAtExpiredMessage)
            ->setCode(ShippingTimeRangeConstraint::SHIPPED_AT_EXPIRED)
            ->assertRaised();
    }

    public function testShippingTimeNotAvailableWithExistingOrderValidation()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getState()
            ->willReturn(Order::STATE_CART);

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('+1 hour'), 5);

        $order = $order->reveal();

        $this->setObject($order);

        $this->shippingDateFilter
            ->accept($order, $shippingTimeRange, Argument::type(\DateTime::class))
            ->willReturn(false);

        $constraint = new ShippingTimeRangeConstraint();
        $violations = $this->validator->validate($shippingTimeRange, $constraint);

        $this->buildViolation($constraint->shippedAtNotAvailableMessage)
            ->setCode(ShippingTimeRangeConstraint::SHIPPED_AT_NOT_AVAILABLE)
            ->assertRaised();
    }

    public function testRestaurantIsClosedValidation()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('+1 hour'), 5);

        $this->setObject($order->reveal());

        $this->shippingDateFilter
            ->accept($order, $shippingTimeRange, Argument::type(\DateTime::class))
            ->willReturn(false);

        $constraint = new ShippingTimeRangeConstraint();
        $violations = $this->validator->validate($shippingTimeRange, $constraint);

        $this->buildViolation($constraint->shippedAtNotAvailableMessage)
            ->setCode(ShippingTimeRangeConstraint::SHIPPED_AT_NOT_AVAILABLE)
            ->assertRaised();
    }

    public function testNoShippingTimeRangeAvailable()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );

        $this->orderTimeHelper
            ->getShippingTimeRange($order->reveal())
            ->willReturn(null);

        $order = $order->reveal();

        $this->setObject($order);

        $this->shippingDateFilter
            ->accept($order, Argument::type(TsRange::class), Argument::type(\DateTime::class))
            ->willReturn(true);

        $constraint = new ShippingTimeRangeConstraint();
        $violations = $this->validator->validate(null, $constraint);

        $this->buildViolation($constraint->shippingTimeRangeNotAvailableMessage)
            ->setCode(ShippingTimeRangeConstraint::SHIPPING_TIME_RANGE_NOT_AVAILABLE)
            ->assertRaised();
    }

    public function testNullWithShippingTimeRangeAvailable()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );

        $this->orderTimeHelper
            ->getShippingTimeRange($order->reveal())
            ->willReturn(
                DateUtils::dateTimeToTsRange(new \DateTime('+1 hour'), 5)
            );

        $order = $order->reveal();

        $this->setObject($order);

        $this->shippingDateFilter
            ->accept($order, Argument::type(TsRange::class), Argument::type(\DateTime::class))
            ->willReturn(true);

        $constraint = new ShippingTimeRangeConstraint();
        $violations = $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testOrderIsValid()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );

        $shippingTimeRange =
            DateUtils::dateTimeToTsRange(new \DateTime('+1 hour'), 5);

        $order = $order->reveal();

        $this->setObject($order);

        $this->shippingDateFilter
            ->accept($order, Argument::type(TsRange::class), Argument::type(\DateTime::class))
            ->willReturn(true);

        $constraint = new ShippingTimeRangeConstraint();
        $violations = $this->validator->validate($shippingTimeRange, $constraint);

        $this->assertNoViolation();
    }
}
