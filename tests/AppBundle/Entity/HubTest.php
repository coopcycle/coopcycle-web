<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class HubTest extends TestCase
{
    use ProphecyTrait;

    private function createRestaurant($products)
    {
        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant->hasProduct(Argument::type(ProductInterface::class))
            ->will(function ($args) use ($products) {
                return in_array($args[0], $products, true);
            });

        return $restaurant->reveal();
    }

    private function createProduct()
    {
        $product = $this->prophesize(ProductInterface::class);

        return $product->reveal();
    }

    private function createProductVariant($product)
    {
        $variant = $this->prophesize(ProductVariantInterface::class);

        $variant->getProduct()->willReturn($product);

        return $variant->reveal();
    }

    private function createOrderItem($variant, $total)
    {
        $item = $this->prophesize(OrderItemInterface::class);

        $item->getVariant()->willReturn($variant);
        $item->getTotal()->willReturn($total);

        return $item->reveal();
    }

    public function testGetItemsTotalForRestaurant()
    {
        $flower1 = $this->createProduct();
        $flower2 = $this->createProduct();

        $flowerShop = $this->createRestaurant([$flower1, $flower2]);

        $flower1Variant = $this->createProductVariant($flower1);
        $flower2Variant = $this->createProductVariant($flower2);

        $bread1 = $this->createProduct();
        $bread2 = $this->createProduct();

        $bakery = $this->createRestaurant([$bread1, $bread2]);

        $bread1Variant = $this->createProductVariant($bread1);
        $bread2Variant = $this->createProductVariant($bread2);

        //

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($flower1Variant, 500),
                $this->createOrderItem($flower2Variant, 700),
                $this->createOrderItem($bread1Variant, 600),
                $this->createOrderItem($bread2Variant, 300),
            ]));

        $hub = new Hub();
        $hub->addRestaurant($flowerShop);
        $hub->addRestaurant($bakery);

        $this->assertEquals(1200, $hub->getItemsTotalForRestaurant($order->reveal(), $flowerShop));
        $this->assertEquals(900, $hub->getItemsTotalForRestaurant($order->reveal(), $bakery));
    }

    public function testGetPercentageForRestaurant()
    {
        $flower1 = $this->createProduct();
        $flower2 = $this->createProduct();

        $flowerShop = $this->createRestaurant([$flower1, $flower2]);

        $flower1Variant = $this->createProductVariant($flower1);
        $flower2Variant = $this->createProductVariant($flower2);

        $bread1 = $this->createProduct();
        $bread2 = $this->createProduct();

        $bakery = $this->createRestaurant([$bread1, $bread2]);

        $bread1Variant = $this->createProductVariant($bread1);
        $bread2Variant = $this->createProductVariant($bread2);

        //

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($flower1Variant, 500),
                $this->createOrderItem($flower2Variant, 700),
                $this->createOrderItem($bread1Variant, 600),
                $this->createOrderItem($bread2Variant, 300),
            ]));
        $order->getItemsTotal()->willReturn(2100);

        $hub = new Hub();
        $hub->addRestaurant($flowerShop);
        $hub->addRestaurant($bakery);

        $this->assertEquals(0.57, $hub->getPercentageForRestaurant($order->reveal(), $flowerShop));
        $this->assertEquals(0.43, $hub->getPercentageForRestaurant($order->reveal(), $bakery));
    }
}
