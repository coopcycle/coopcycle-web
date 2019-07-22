<?php

namespace AppBundle\Validator;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Entity\Contract;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ValidationUtils;
use AppBundle\Validator\Constraints\Order as OrderConstraint;
use AppBundle\Validator\Constraints\OrderValidator;
use Prophecy\Argument;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class OrderValidatorTest extends ConstraintValidatorTestCase
{
    protected $routing;
    protected $shippingDateFilter;

    public function setUp(): void
    {
        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->shippingDateFilter = $this->prophesize(ShippingDateFilter::class);

        parent::setUp();
    }

    protected function createValidator()
    {
        return new OrderValidator(
            $this->routing->reveal(),
            new ExpressionLanguage(),
            $this->shippingDateFilter->reveal(),
            'en'
        );
    }

    private function prophesizeGetRawResponse(GeoCoordinates $origin, GeoCoordinates $destination, $distance, $duration)
    {
        $this->routing
            ->getRawResponse($origin, $destination)
            ->willReturn([
                'routes' => [
                    [
                        'distance' => $distance,
                        'duration' => $duration
                    ]
                ]
            ]);
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
        Address $shippingAddress,
        $minimumCartAmount,
        $maxDistanceExpression,
        $canDeliver)
    {
        $restaurant = $this->prophesize(Restaurant::class);

        $restaurant
            ->getAddress()
            ->willReturn($address);

        $restaurant
            ->getMinimumCartAmount()
            ->willReturn($minimumCartAmount);

        $restaurant
            ->getDeliveryPerimeterExpression()
            ->willReturn($maxDistanceExpression);

        $restaurant
            ->canDeliverAddress(Argument::any(), Argument::any(), Argument::any())
            ->willReturn($canDeliver);

        return $restaurant;
    }

    private function createOrderProphecy(Restaurant $restaurant, Address $shippingAddress)
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

        return $order;
    }

    public function testDistanceValidation()
    {
        $shippedAt = new \DateTime();
        $shippedAt->modify('+1 day');

        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = false
        );
        $restaurant
            ->isOpen($shippedAt)
            ->willReturn(true);

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );
        $order
            ->getShippedAt()
            ->willReturn($shippedAt);
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

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->addressTooFarMessage)
            ->atPath('property.path.shippingAddress')
            ->setCode(OrderConstraint::ADDRESS_TOO_FAR)
            ->assertRaised();
    }

    public function testPastDateWithNewOrderValidation()
    {
        $shippedAt = new \DateTime();
        $shippedAt->modify('-1 hour');

        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );
        $restaurant
            ->isOpen($shippedAt)
            ->willReturn(true);

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );
        $order
            ->getShippedAt()
            ->willReturn($shippedAt);
        $order
            ->getItemsTotal()
            ->willReturn(2500);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $distance = 2500,
            $duration = 300
        );

        $order = $order->reveal();

        $this->shippingDateFilter
            ->accept($order, $shippedAt, Argument::type(\DateTime::class))
            ->willReturn(false);

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order, $constraint);

        $this->buildViolation($constraint->shippedAtExpiredMessage)
            ->atPath('property.path.shippedAt')
            ->setCode(OrderConstraint::SHIPPED_AT_EXPIRED)
            ->assertRaised();
    }

    public function testPastDateWithExistingOrderValidation()
    {
        $shippedAt = new \DateTime();
        $shippedAt->modify('-1 hour');

        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );
        $restaurant
            ->isOpen($shippedAt)
            ->willReturn(true);

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
        $order
            ->getShippedAt()
            ->willReturn($shippedAt);
        $order
            ->getItemsTotal()
            ->willReturn(2500);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $distance = 2500,
            $duration = 300
        );

        $order = $order->reveal();

        $this->shippingDateFilter
            ->accept($order, $shippedAt, Argument::type(\DateTime::class))
            ->willReturn(false);

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order, $constraint);

        $this->buildViolation($constraint->shippedAtExpiredMessage)
            ->atPath('property.path.shippedAt')
            ->setCode(OrderConstraint::SHIPPED_AT_EXPIRED)
            ->assertRaised();
    }

    public function testShippedAtNotAvailableWithExistingOrderValidation()
    {
        $shippedAt = new \DateTime();
        $shippedAt->modify('+10 minutes');

        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );
        $restaurant
            ->isOpen($shippedAt)
            ->willReturn(true);

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
        $order
            ->getShippedAt()
            ->willReturn($shippedAt);
        $order
            ->getItemsTotal()
            ->willReturn(2500);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $distance = 2500,
            $duration = 300
        );

        $order = $order->reveal();

        $this->shippingDateFilter
            ->accept($order, $shippedAt, Argument::type(\DateTime::class))
            ->willReturn(false);

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order, $constraint);

        $this->buildViolation($constraint->shippedAtNotAvailableMessage)
            ->atPath('property.path.shippedAt')
            ->setCode(OrderConstraint::SHIPPED_AT_NOT_AVAILABLE)
            ->assertRaised();
    }

    public function testRestaurantIsClosedValidation()
    {
        $shippedAt = new \DateTime();
        $shippedAt->modify('+1 hour');

        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );
        $restaurant
            ->isOpen($shippedAt)
            ->willReturn(false);

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );
        $order
            ->getShippedAt()
            ->willReturn($shippedAt);
        $order
            ->getItemsTotal()
            ->willReturn(2500);
        $order
            ->containsDisabledProduct()
            ->willReturn(false);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $distance = 2500,
            $duration = 300
        );

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->restaurantClosedMessage)
            ->atPath('property.path.shippedAt')
            ->setParameter('%date%', $shippedAt->format('Y-m-d H:i:s'))
            ->assertRaised();
    }

    public function testMinimumAmountValidation()
    {
        $shippedAt = new \DateTime();
        $shippedAt->modify('+1 day');

        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );
        $restaurant
            ->isOpen($shippedAt)
            ->willReturn(true);

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );
        $order
            ->getShippedAt()
            ->willReturn($shippedAt);
        $order
            ->getItemsTotal()
            ->willReturn(500);
        $order
            ->containsDisabledProduct()
            ->willReturn(false);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $maxDistanceExpression = 'distance < 1500',
            $duration = 300
        );

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->totalIncludingTaxTooLowMessage)
            ->atPath('property.path.total')
            ->setParameter('%minimum_amount%', 20.00)
            ->assertRaised();
    }

    public function testOrderWithStateNewCantHaveNullShippingTime()
    {
        $shippedAt = new \DateTime();
        $shippedAt->modify('+1 hour');

        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );
        $restaurant
            ->isOpen($shippedAt)
            ->willReturn(true);

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );

        $order
            ->getItemsTotal()
            ->willReturn(2500);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getState()
            ->willReturn(Order::STATE_NEW);
        $order
            ->getShippedAt()
            ->willReturn(null);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $maxDistanceExpression = 'distance < 1500',
            $duration = 300
        );

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->shippedAtNotEmptyMessage)
            ->atPath('property.path.shippedAt')
            ->setCode(OrderConstraint::SHIPPED_AT_NOT_EMPTY)
            ->assertRaised();
    }

    public function testOrderWithStateCartCantContainDisabledProducts()
    {
        $shippedAt = new \DateTime();
        $shippedAt->modify('+1 hour');

        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );
        $restaurant
            ->isOpen($shippedAt)
            ->willReturn(true);

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );

        $order
            ->getItemsTotal()
            ->willReturn(2500);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getState()
            ->willReturn(Order::STATE_CART);
        $order
            ->getShippedAt()
            ->willReturn(null);
        $order
            ->containsDisabledProduct()
            ->willReturn(true);

        $this->prophesizeGetRawResponse(
            $restaurantAddressCoords,
            $shippingAddressCoords,
            $maxDistanceExpression = 'distance < 1500',
            $duration = 300
        );

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->containsDisabledProductMessage)
            ->atPath('property.path.items')
            ->setCode(OrderConstraint::CONTAINS_DISABLED_PRODUCT)
            ->assertRaised();
    }

    public function testOrderIsValid()
    {
        $shippedAt = new \DateTime();
        $shippedAt->modify('+1 hour');

        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );
        $restaurant
            ->isOpen($shippedAt)
            ->willReturn(true);

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
        );
        $order
            ->getShippedAt()
            ->willReturn($shippedAt);
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

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->assertNoViolation();
    }
}
