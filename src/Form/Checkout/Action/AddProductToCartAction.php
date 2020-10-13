<?php

namespace AppBundle\Form\Checkout\Action;

use AppBundle\Form\Checkout\Action\Validator\AddProductToCart as AssertAddProductToCart;

/**
 * @AssertAddProductToCart
 */
class AddProductToCartAction
{
    public $restaurant;
    public $product;
    public $cart;
    public $clear = false;
}
