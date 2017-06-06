<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Order;
use AppBundle\Entity\OrderItem;
use AppBundle\Entity\Product;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testAddProduct()
    {
        $order = new Order();

        $pizza = new Product();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $salad = new Product();
        $salad
            ->setName('Salad')
            ->setPrice(5);

        $order->addProduct($pizza, 1);
        $order->addProduct($pizza, 3);

        $order->addProduct($salad, 2);

        $this->assertCount(2, $order->getOrderedItem());

        $pizzaItem = $order->getOrderedItem()->filter(function (OrderItem $orderItem) use ($pizza) {
            return $orderItem->getProduct() === $pizza;
        })->first();

        $saladItem = $order->getOrderedItem()->filter(function (OrderItem $orderItem) use ($salad) {
            return $orderItem->getProduct() === $salad;
        })->first();


        $this->assertEquals(4, $pizzaItem->getQuantity());
        $this->assertEquals(2, $saladItem->getQuantity());
    }

    public function testTotal()
    {
        $order = new Order();

        $pizza = new Product();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $salad = new Product();
        $salad
            ->setName('Salad')
            ->setPrice(5);

        $order->addProduct($pizza, 4);
        $order->addProduct($salad, 2);

        $this->assertEquals(50, $order->getTotal());
    }
}
