<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Order;
use AppBundle\Entity\OrderItem;
use AppBundle\Entity\MenuItem;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testAddMenuItem()
    {
        $order = new Order();

        $pizza = new MenuItem();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $salad = new MenuItem();
        $salad
            ->setName('Salad')
            ->setPrice(5);

        $order->addMenuItem($pizza, 1);
        $order->addMenuItem($pizza, 3);

        $order->addMenuItem($salad, 2);

        $this->assertCount(2, $order->getOrderedItem());

        $pizzaItem = $order->getOrderedItem()->filter(function (OrderItem $orderItem) use ($pizza) {
            return $orderItem->getMenuItem() === $pizza;
        })->first();

        $saladItem = $order->getOrderedItem()->filter(function (OrderItem $orderItem) use ($salad) {
            return $orderItem->getMenuItem() === $salad;
        })->first();


        $this->assertEquals(4, $pizzaItem->getQuantity());
        $this->assertEquals(2, $saladItem->getQuantity());
    }

    public function testTotal()
    {
        $order = new Order();

        $pizza = new MenuItem();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $salad = new MenuItem();
        $salad
            ->setName('Salad')
            ->setPrice(5);

        $order->addMenuItem($pizza, 4);
        $order->addMenuItem($salad, 2);

        $this->assertEquals(50, $order->getTotal());
    }
}
