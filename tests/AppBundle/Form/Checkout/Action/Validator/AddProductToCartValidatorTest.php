<?php

namespace Tests\AppBundle\Form\Checkout\Action\Validator;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Form\Checkout\Action\AddProductToCartAction;
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

    public function testDisabledProduct()
    {
        $restaurant = $this->prophesize(LocalBusiness::class);
        $this->resolver->resolve()->willReturn($restaurant->reveal());

        $product = $this->prophesize(Product::class);
        $product->isEnabled()->willReturn(false);
        $product->getCode()->willReturn('ABCDEF');

        $action = new AddProductToCartAction();
        $action->product = $product->reveal();

        $constraint = new AddProductToCart();
        $violations = $this->validator->validate($action, $constraint);

        $this
            ->buildViolation($constraint->productDisabled)
            ->atPath('property.path.items')
            ->setParameter('%code%', 'ABCDEF')
            ->assertRaised();
    }

    public function testProductNotBelongingToRestaurant()
    {
        $product = $this->prophesize(Product::class);
        $product->isEnabled()->willReturn(true);
        $product->getCode()->willReturn('ABCDEF');

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->hasProduct($product->reveal())->willReturn(false);

        $action = new AddProductToCartAction();
        $action->product = $product->reveal();

        $this->resolver->resolve()->willReturn($restaurant->reveal());

        $constraint = new AddProductToCart();
        $violations = $this->validator->validate($action, $constraint);

        $this
            ->buildViolation($constraint->productNotBelongsTo)
            ->atPath('property.path.restaurant')
            ->setParameter('%code%', 'ABCDEF')
            ->assertRaised();
    }

    public function testDifferentRestaurantWithoutClear()
    {
        $restaurant = $this->prophesize(LocalBusiness::class);
        $product = $this->prophesize(Product::class);
        $cart = $this->prophesize(Order::class);

        $product->isEnabled()->willReturn(true);
        $restaurant->hasProduct($product->reveal())->willReturn(true);

        $this->resolver->resolve()->willReturn($restaurant->reveal());
        $this->resolver->accept($cart->reveal())->willReturn(false);

        $action = new AddProductToCartAction();
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
        $this->resolver->accept($cart->reveal())->willReturn(false);

        $action = new AddProductToCartAction();
        $action->product = $product->reveal();
        $action->cart = $cart->reveal();
        $action->clear = true;

        $constraint = new AddProductToCart();
        $violations = $this->validator->validate($action, $constraint);

        $this->assertNoViolation();
    }
}
