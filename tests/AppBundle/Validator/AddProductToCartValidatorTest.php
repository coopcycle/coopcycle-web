<?php

namespace Tests\AppBundle\Validator;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Form\Checkout\Action\AddProductToCartAction as CheckoutAddProductToCart;
use AppBundle\Form\Checkout\Action\Validator\AddProductToCart;
use AppBundle\Form\Checkout\Action\Validator\AddProductToCartValidator;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class AddProductToCartValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    public function setUp() :void
    {
        parent::setUp();
    }

    protected function createValidator()
    {
        return new AddProductToCartValidator();
    }

    public function testDifferentRestaurantWithoutClear()
    {
        $restaurant = $this->prophesize(LocalBusiness::class);
        $product = $this->prophesize(Product::class);
        $cart = $this->prophesize(Order::class);

        $product->isEnabled()->willReturn(true);
        $restaurant->hasProduct($product->reveal())->willReturn(true);

        $action = new CheckoutAddProductToCart();

        $action->restaurant = $restaurant->reveal();
        $action->product = $product->reveal();
        $action->cart = $cart->reveal();
        $action->clear = false;

        $constraint = new AddProductToCart();
        $violations = $this->validator->validate($action, $constraint);

        $this
            ->buildViolation($constraint->notSameRestaurant)
            ->atPath('property.path.restaurant')
            ->assertRaised();
    }

    public function testDifferentRestaurantWithClear()
    {
        $restaurant = $this->prophesize(LocalBusiness::class);
        $product = $this->prophesize(Product::class);
        $cart = $this->prophesize(Order::class);

        $product->isEnabled()->willReturn(true);
        $restaurant->hasProduct($product->reveal())->willReturn(true);

        $action = new CheckoutAddProductToCart();

        $action->restaurant = $restaurant->reveal();
        $action->product = $product->reveal();
        $action->cart = $cart->reveal();
        $action->clear = true;

        $constraint = new AddProductToCart();
        $violations = $this->validator->validate($action, $constraint);

        $this->assertNoViolation();
    }
}
