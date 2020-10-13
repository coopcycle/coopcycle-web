<?php

namespace Tests\AppBundle\Form\Checkout\Action\Validator;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Form\Checkout\Action\AddProductToCartAction;
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

    public function testDisabledProduct()
    {
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
        $action->restaurant = $restaurant->reveal();

        $constraint = new AddProductToCart();
        $violations = $this->validator->validate($action, $constraint);

        $this
            ->buildViolation($constraint->productNotBelongsTo)
            ->atPath('property.path.restaurant')
            ->setParameter('%code%', 'ABCDEF')
            ->assertRaised();
    }

    public function testNotSameRestaurantInCart()
    {
        $product = $this->prophesize(Product::class);
        $product->isEnabled()->willReturn(true);

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->hasProduct($product->reveal())->willReturn(true);

        $otherRestaurant = $this->prophesize(LocalBusiness::class);

        $cart = $this->prophesize(Order::class);
        $cart->getRestaurant()->willReturn($otherRestaurant->reveal());

        $action = new AddProductToCartAction();
        $action->product = $product->reveal();
        $action->restaurant = $restaurant->reveal();
        $action->cart = $cart->reveal();
        $action->clear = false;

        $constraint = new AddProductToCart();
        $violations = $this->validator->validate($action, $constraint);

        $this
            ->buildViolation($constraint->notSameRestaurant)
            ->atPath('property.path.restaurant')
            ->assertRaised();
    }

    public function testNotSameRestaurantInCartWithClear()
    {
        $product = $this->prophesize(Product::class);
        $product->isEnabled()->willReturn(true);

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->hasProduct($product->reveal())->willReturn(true);

        $otherRestaurant = $this->prophesize(LocalBusiness::class);

        $cart = $this->prophesize(Order::class);
        $cart->getRestaurant()->willReturn($otherRestaurant->reveal());

        $action = new AddProductToCartAction();
        $action->product = $product->reveal();
        $action->restaurant = $restaurant->reveal();
        $action->cart = $cart->reveal();
        $action->clear = true;

        $constraint = new AddProductToCart();
        $violations = $this->validator->validate($action, $constraint);

        $this->assertNoViolation();
    }
}
