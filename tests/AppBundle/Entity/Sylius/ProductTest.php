<?php

namespace Tests\AppBundle\Entity\Sylius;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testSetReusablePackagingThrowsLogicException()
    {
        $this->expectException(\LogicException::class);

        $reusablePackaging = new ReusablePackaging();
        $otherReusablePackaging = new ReusablePackaging();

        $restaurant = new Restaurant();
        $restaurant->setDepositRefundEnabled(true);
        $restaurant->addReusablePackaging($reusablePackaging);

        $product = new Product();
        $product->setRestaurant($restaurant);
        $product->setReusablePackaging($otherReusablePackaging);
    }
}
