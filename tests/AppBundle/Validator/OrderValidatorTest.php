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

    protected $priceFormatter;

    public function setUp(): void
    {
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
            $this->priceFormatter->reveal()
        );
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
        $maxDistanceExpression,
        $canDeliver)
    {
        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getAddress()
            ->willReturn($address);

        $restaurant
            ->getDeliveryPerimeterExpression()
            ->willReturn($maxDistanceExpression);
        $restaurant
            ->canDeliverAddress(Argument::any(), Argument::any(), Argument::any())
            ->willReturn($canDeliver);
        $restaurant
            ->getOpeningHoursBehavior()
            ->willReturn('asap');

        return $restaurant;
    }

    private function createOrderProphecy(LocalBusiness $restaurant, ?Address $shippingAddress, int $minimumCartAmount, $takeaway = false)
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

        $fulfillmentMethod = new FulfillmentMethod();
        $fulfillmentMethod->setMinimumAmount($minimumCartAmount);

        $order
            ->getFulfillmentMethodObject()
            ->willReturn($fulfillmentMethod);

        return $order;
    }

    public function testMinimumAmountValidation()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddress($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress,
            $minimumCartAmount = 2000
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
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000
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
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress->reveal(),
            $minimumCartAmount = 2000
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

        $constraint = new OrderConstraint();
        $violations = $this->validator->validate($order->reveal(), $constraint);

        $this->buildViolation($constraint->containsDisabledProductMessage)
            ->atPath('property.path.items')
            ->setCode(OrderConstraint::CONTAINS_DISABLED_PRODUCT)
            ->assertRaised();
    }

    public function testOrderIsValid()
    {
        $shippingAddressCoords = new GeoCoordinates();
        $restaurantAddressCoords = new GeoCoordinates();

        $shippingAddress = $this->createAddress($shippingAddressCoords);
        $restaurantAddress = $this->createAddressProphecy($restaurantAddressCoords);

        $restaurant = $this->createRestaurantProphecy(
            $restaurantAddress->reveal(),
            $maxDistanceExpression = 'distance < 3000',
            $canDeliver = true
        );

        $order = $this->createOrderProphecy(
            $restaurant->reveal(),
            $shippingAddress,
            $minimumCartAmount = 2000
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
}
