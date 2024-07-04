<?php

namespace Tests\AppBundle\Entity\Sylius;

use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Entity\ReusablePackagings;
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

    private function createProduct($isReusablePackagingEnabled = false,
        $hasReusablePackagings = false, array $reusablePackagings = [])
    {
        $product = $this->prophesize(ProductInterface::class);

        $product->isReusablePackagingEnabled()->willReturn($isReusablePackagingEnabled);
        $product->hasReusablePackagings()->willReturn($hasReusablePackagings);

        if (!empty($reusablePackagings)) {
            $product->getReusablePackagings()->willReturn(new ArrayCollection($reusablePackagings));
        }

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
        $item->getQuantity()->willReturn(1);

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

    public function testGetFormatsToDeliverForLoopeatGroupsContainers()
    {
        $order = new Order();

        $reusablePackaging = new ReusablePackaging();
        $reusablePackaging->setData(['id' => 1]);

        $reusablePackagings = new ReusablePackagings();
        $reusablePackagings->setReusablePackaging($reusablePackaging);
        $reusablePackagings->setUnits(1);

        $product1 = $this->createProduct(true, true, [ $reusablePackagings ]);
        $product2 = $this->createProduct(true, true, [ $reusablePackagings ]);
        $product3 = $this->createProduct(true, true, [ $reusablePackagings ]);

        $product1Variant = $this->createProductVariant($product1);
        $product2Variant = $this->createProductVariant($product2);
        $product3Variant = $this->createProductVariant($product3);

        $order->addItem($this->createOrderItem($product1Variant, 2000));
        $order->addItem($this->createOrderItem($product2Variant,  700));
        $order->addItem($this->createOrderItem($product3Variant,   300));

        $expectedFormats = [
            ['format_id' => 1, 'quantity' => 3]
        ];

        $this->assertEquals($expectedFormats, $order->getFormatsToDeliverForLoopeat());
    }
}
