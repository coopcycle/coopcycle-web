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
        $this->markTestSkipped();

        $this->expectException(\LogicException::class);

        $reusablePackaging = new ReusablePackaging();
        $otherReusablePackaging = new ReusablePackaging();

        $otherRestaurant = new Restaurant();
        $otherRestaurant->addReusablePackaging($otherReusablePackaging);

        $product = new Product();

        $restaurant = new Restaurant();
        $restaurant->setDepositRefundEnabled(true);
        $restaurant->addReusablePackaging($reusablePackaging);
        $restaurant->addProduct($product);

        $product->setReusablePackaging($otherReusablePackaging);
    }
}
