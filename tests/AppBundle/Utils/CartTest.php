<?php

namespace AppBundle\Utils;

use PHPUnit\Framework\TestCase;
use AppBundle\Utils\Cart;
use AppBundle\Entity\MenuItem;
use AppBundle\Entity\Restaurant;

class CartTest extends TestCase
{
    private static $itemCounter = 0;

    private function createMenuItem($name, $price)
    {
        $item = $this->prophesize(MenuItem::class);

        $item->getId()->willReturn(++self::$itemCounter);
        $item->getName()->willReturn($name);
        $item->getPrice()->willReturn($price);

        return $item->reveal();
    }

    public function testTotal()
    {
        $cart = new Cart();

        $item1 = $this->createMenuItem('Item 1', 5);
        $item2 = $this->createMenuItem('Item 2', 10);

        $cart->addItem($item1);
        $cart->addItem($item2);
        $cart->addItem($item2);

        $this->assertEquals(25, $cart->getTotal());

        $cart->removeItem($item2);

        $this->assertEquals(5, $cart->getTotal());
    }
}
