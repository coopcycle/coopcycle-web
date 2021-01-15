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
use AppBundle\Validator\Constraints\Order as OrderConstraint;
use AppBundle\Validator\Constraints\OrderValidator;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class OrderValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    protected $routing;
    protected $priceFormatter;

    public function setUp(): void
    {
        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->priceFormatter = $this->prophesize(PriceFormatter::class);

        $this->priceFormatter
            ->formatWithSymbol(Argument::type('int'))
            ->will(function ($args) {
                return sprintf('%s €', number_format($args[0] / 100, 2));
            });

        parent::setUp();
    }

    protected function createValidator()
    {
        return new OrderValidator(
            $this->routing->reveal(),
            new ExpressionLanguage(),
            $this->priceFormatter->reveal()
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

        $shippingAddress = $this->createAddressProphecy($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $minimumCartAmount = 2000,
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = false
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal()
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

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->addressTooFarMessage)
            ->atPath('property.path.shippingAddress')
            ->setCode(OrderConstraint::ADDRESS_TOO_FAR)
            ->assertRaised();
    }

    public function testMinimumAmountValidation()
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

        $order
            ->getShippingTimeRange()
            ->willReturn($shippingTimeRange);
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
            ->setParameter('%minimum_amount%', '20.00 €')
            ->assertRaised();
    }

    public function testOrderWithStateNewCantHaveNullShippingTime()
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
            ->getItemsTotal()
            ->willReturn(2500);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getState()
            ->willReturn(Order::STATE_NEW);
        $order
            ->getShippingTimeRange()
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
            ->atPath('property.path.shippingTimeRange')
            ->setCode(OrderConstraint::SHIPPED_AT_NOT_EMPTY)
            ->assertRaised();
    }

    public function testOrderWithStateCartCantContainDisabledProducts()
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
            ->getItemsTotal()
            ->willReturn(2500);
        $order
            ->getId()
            ->willReturn(1);
        $order
            ->getState()
            ->willReturn(Order::STATE_CART);
        $order
            ->getShippingTimeRange()
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

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->addressNotSetMessage)
            ->atPath('property.path.shippingAddress')
            ->setCode(OrderConstraint::ADDRESS_NOT_SET)
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

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

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

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->assertNoViolation();
    }
}
