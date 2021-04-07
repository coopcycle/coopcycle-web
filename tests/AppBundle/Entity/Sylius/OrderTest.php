<?php

namespace Tests\AppBundle\Entity\Sylius;

use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class OrderTest extends TestCase
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

        $item->setOrder(Argument::type(OrderInterface::class))->shouldBeCalled();

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

        $order = new Order();

        $order->addItem($this->createOrderItem($flower1Variant, 500));
        $order->addItem($this->createOrderItem($flower2Variant, 700));
        $order->addItem($this->createOrderItem($bread1Variant,  600));
        $order->addItem($this->createOrderItem($bread2Variant,  300));

        $this->assertEquals(1200, $order->getItemsTotalForRestaurant($flowerShop));
        $this->assertEquals(900, $order->getItemsTotalForRestaurant($bakery));
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

        $beer1 = $this->createProduct();
        $beer2 = $this->createProduct();

        $brewery = $this->createRestaurant([$beer1, $beer2]);

        $beer1Variant = $this->createProductVariant($beer1);
        $beer2Variant = $this->createProductVariant($beer2);

        $order = new Order();

        $order->addItem($this->createOrderItem($flower1Variant, 2000));
        $order->addItem($this->createOrderItem($flower2Variant,  700));
        $order->addItem($this->createOrderItem($bread1Variant,   300));
        $order->addItem($this->createOrderItem($bread2Variant,   300));
        $order->addItem($this->createOrderItem($beer1Variant,    300));
        $order->addItem($this->createOrderItem($beer2Variant,    400));

        $this->assertEquals(0.6750, $order->getPercentageForRestaurant($flowerShop));
        $this->assertEquals(0.1500, $order->getPercentageForRestaurant($bakery));
        $this->assertEquals(0.1750, $order->getPercentageForRestaurant($brewery));
    }

    public function testGetPercentageForRestaurantDoesNotDivideByZero()
    {
        $bakery = $this->createRestaurant([]);

        $order = new Order();

        $this->assertEquals(0.0, $order->getPercentageForRestaurant($bakery));
    }
}
