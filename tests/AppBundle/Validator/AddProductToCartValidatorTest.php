<?php

namespace Tests\AppBundle\Validator;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Form\Checkout\Action\AddProductToCartAction as CheckoutAddProductToCart;
use AppBundle\Form\Checkout\Action\Validator\AddProductToCart;
use AppBundle\Form\Checkout\Action\Validator\AddProductToCartValidator;
use AppBundle\Sylius\Cart\RestaurantResolver;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class AddProductToCartValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    public function setUp() :void
    {
        $this->resolver = $this->prophesize(RestaurantResolver::class);

        parent::setUp();
    }

    protected function createValidator()
    {
        return new AddProductToCartValidator(
            $this->resolver->reveal()
        );
    }

    public function testDifferentRestaurantWithoutClear()
    {
        $restaurant = $this->prophesize(LocalBusiness::class);
        $product = $this->prophesize(Product::class);
        $cart = $this->prophesize(Order::class);

        $product->isEnabled()->willReturn(true);
        $restaurant->hasProduct($product->reveal())->willReturn(true);

        $this->resolver->resolve()->willReturn($restaurant->reveal());

        $action = new CheckoutAddProductToCart();

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

        $this->resolver->resolve()->willReturn($restaurant->reveal());

        $action = new CheckoutAddProductToCart();

        $action->product = $product->reveal();
        $action->cart = $cart->reveal();
        $action->clear = true;

        $constraint = new AddProductToCart();
        $violations = $this->validator->validate($action, $constraint);

        $this->assertNoViolation();
    }
}
