<?php

namespace AppBundle\Utils;

use AppBundle\BaseTest;
use AppBundle\Entity\Menu\MenuItem;


class CartTest extends BaseTest
{

    public function testTotal()
    {
        $cart = new Cart();

        $item1 = $this->createMenuItem('Item 1', 5);
        $item2 = $this->createMenuItem('Item 2', 10);

        $cart->addItem($item1);
        $cart->addItem($item2);
        $cart->addItem($item2);

        $this->assertEquals(25, $cart->getTotal());

        $cartItem = new CartItem($item2, 0, []);

        $cart->removeItem($cartItem->getKeyHash());

        $this->assertEquals(5, $cart->getTotal());
    }

    public function testTotalWithFreeModifier () {
        $cart = new Cart();

        $item1 = $this->createMenuItem('Item 1', 5);
        $id1 = $item1->getId();

        $item2 = $this->createModifier('Item 2', 10);
        $item3 = $this->createModifier('Item 3', 10);

        $modifier = $this->createMenuItemModifier($item1, [$item2, $item3], 'FREE', 5);

        $item1 = $this->doctrine
                        ->getRepository(MenuItem::class)
                        ->findOneBy(['id' => $id1]);

        $cart->addItem($item1, 1, [$modifier->getId() => [$item2->getId()]]);

        $this->assertEquals(5, $cart->getTotal());
    }

    public function testTotalWithPayingModifier () {
        $cart = new Cart();

        $item1 = $this->createMenuItem('Item 1', 5);
        $item2 = $this->createModifier('Item 2', 10);
        $item3 = $this->createModifier('Item 3', 10);

        $modifier = $this->createMenuItemModifier($item1, [$item2, $item3], 'ADD_MENUITEM_PRICE', 5);

        $cart->addItem($item1, 1, [$modifier->getId() => [$item2->getId()]]);

        $this->assertEquals(15, $cart->getTotal());
    }

    public function testTotalWithFlatModifierPrice () {
        $cart = new Cart();

        $item1 = $this->createMenuItem('Item 1', 5);
        $item2 = $this->createModifier('Item 2', 10);
        $item3 = $this->createModifier('Item 3', 10);

        $modifier = $this->createMenuItemModifier($item1, [$item2, $item3], 'ADD_MODIFIER_PRICE', 5);

        $cart->addItem($item1, 1, [$modifier->getId() => [$item2->getId()]]);

        $this->assertEquals(10, $cart->getTotal());
    }
}
