<?php

namespace AppBundle\Utils;

use AppBundle\BaseTest;
use AppBundle\Entity\Menu;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Menu\MenuSection;
use AppBundle\Entity\Restaurant;


class CartTest extends BaseTest
{

    public function setUp() {
        parent::setUp();

        $this->restaurant = new Restaurant();
        $this->restaurant->setId(1);
        $menu = new Menu();
        $menu->setRestaurant($this->restaurant);
        $this->menuSection = new MenuSection();
        $this->menuSection->setMenu($menu);
    }

    public function testTotal()
    {
        $cart = new Cart();

        $item1 = $this->createMenuItem('Item 1', 5);
        $item2 = $this->createMenuItem('Item 2', 10);

        $item1->setSection($this->menuSection);
        $item2->setSection($this->menuSection);

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

        $item1->setSection($this->menuSection);

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
        $item1->setSection($this->menuSection);

        $item2 = $this->createModifier('Item 2', 10);
        $item3 = $this->createModifier('Item 3', 10);

        $modifier = $this->createMenuItemModifier($item1, [$item2, $item3], 'ADD_MENUITEM_PRICE', 5);

        $cart->addItem($item1, 1, [$modifier->getId() => [$item2->getId()]]);

        $this->assertEquals(15, $cart->getTotal());
    }

    public function testTotalWithFlatModifierPrice () {
        $cart = new Cart();

        $item1 = $this->createMenuItem('Item 1', 5);
        $item1->setSection($this->menuSection);

        $item2 = $this->createModifier('Item 2', 10);
        $item3 = $this->createModifier('Item 3', 10);

        $modifier = $this->createMenuItemModifier($item1, [$item2, $item3], 'ADD_MODIFIER_PRICE', 5);

        $cart->addItem($item1, 1, [$modifier->getId() => [$item2->getId()]]);

        $this->assertEquals(10, $cart->getTotal());
    }

    public function testCantAddUnavailable () {
        $cart = new Cart();
        $item1 = $this->createMenuItem('Item 1', 5);
        $item1->setSection($this->menuSection);

        $this->expectException(UnavailableProductException::class);
        $this->expectExceptionMessage(sprintf('Product %s is not available', $item1->getId()));

        $item1->setIsAvailable(false);
        $cart->addItem($item1, 1);
    }

    public function testRestaurantMismatch() {
        $restaurant = new Restaurant();
        $restaurant->setId(2);
        $restaurant->setName('Test restaurant');

        $cart = new Cart($restaurant);
        $item1 = $this->createMenuItem('Item 1', 5);
        $item1->setSection($this->menuSection);

        $this->expectException(RestaurantMismatchException::class);
        $this->expectExceptionMessage(sprintf('Product %s doesn\'t belong to restaurant %s', $item1->getId(), $restaurant->getId()));

        $cart->addItem($item1, 1);
    }
}
