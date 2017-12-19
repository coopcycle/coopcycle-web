<?php

namespace AppBundle\Utils;

use AppBundle\BaseTest;
use AppBundle\Entity\Contract;
use AppBundle\Entity\Menu;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Menu\MenuSection;
use AppBundle\Entity\Restaurant;

class CartTest extends BaseTest
{
    private $restaurant;
    private $menuSection;
    private $taxCategory;

    public function setUp()
    {
        parent::setUp();

        $contract = new Contract();
        $contract->setFlatDeliveryPrice(3.5);
        $contract->setMinimumCartAmount(10);

        $this->restaurant = new Restaurant();
        $this->restaurant->setId(1);
        $this->restaurant->setContract($contract);

        $menu = new Menu();
        $menu->setRestaurant($this->restaurant);

        $this->menuSection = new MenuSection();
        $this->menuSection->setMenu($menu);

        $this->taxCategory = $this->createTaxCategory('Default', 'default', 'TVA 10%', 'tva_10', 10.00);
    }

    public function testTotalWithNoItems()
    {
        $cart = new Cart($this->restaurant);

        $this->assertEquals(0, $cart->getTotal());
    }

    public function testTotal()
    {
        $cart = new Cart($this->restaurant);

        $item1 = $this->createMenuItem('Item 1', 5, $this->taxCategory);
        $item2 = $this->createMenuItem('Item 2', 10, $this->taxCategory);

        $item1->setSection($this->menuSection);
        $item2->setSection($this->menuSection);

        $cart->addItem($item1);
        $cart->addItem($item2);
        $cart->addItem($item2);

        $this->assertEquals(25 + 3.5, $cart->getTotal());

        $cartItem = new CartItem($item2, 0, []);

        $cart->removeItem($cartItem->getKeyHash());

        $this->assertEquals(5 + 3.5, $cart->getTotal());
    }

    public function testTotalWithFreeModifier()
    {
        $cart = new Cart($this->restaurant);

        $item1 = $this->createMenuItem('Item 1', 5, $this->taxCategory);
        $id1 = $item1->getId();

        $item1->setSection($this->menuSection);

        $item2 = $this->createModifier('Item 2', 10, $this->taxCategory);
        $item3 = $this->createModifier('Item 3', 10, $this->taxCategory);

        $modifier = $this->createMenuItemModifier($item1, [$item2, $item3], 'FREE', 5);

        $item1 = $this->doctrine
                        ->getRepository(MenuItem::class)
                        ->findOneBy(['id' => $id1]);

        $cart->addItem($item1, 1, [$modifier->getId() => [$item2->getId()]]);

        $this->assertEquals(5 + 3.5, $cart->getTotal());
    }

    public function testTotalWithPayingModifier()
    {
        $cart = new Cart($this->restaurant);

        $item1 = $this->createMenuItem('Item 1', 5, $this->taxCategory);
        $item1->setSection($this->menuSection);

        $item2 = $this->createModifier('Item 2', 10, $this->taxCategory);
        $item3 = $this->createModifier('Item 3', 10, $this->taxCategory);

        $modifier = $this->createMenuItemModifier($item1, [$item2, $item3], 'ADD_MENUITEM_PRICE', 5);

        $cart->addItem($item1, 1, [$modifier->getId() => [$item2->getId()]]);

        $this->assertEquals(15 + 3.5, $cart->getTotal());
    }

    public function testTotalWithFlatModifierPrice()
    {
        $cart = new Cart($this->restaurant);

        $item1 = $this->createMenuItem('Item 1', 5, $this->taxCategory);
        $item1->setSection($this->menuSection);

        $item2 = $this->createModifier('Item 2', 10, $this->taxCategory);
        $item3 = $this->createModifier('Item 3', 10, $this->taxCategory);

        $modifier = $this->createMenuItemModifier($item1, [$item2, $item3], 'ADD_MODIFIER_PRICE', 5);

        $cart->addItem($item1, 1, [$modifier->getId() => [$item2->getId()]]);

        $this->assertEquals(10 + 3.5, $cart->getTotal());
    }

    public function testCantAddUnavailable()
    {
        $cart = new Cart($this->restaurant);
        $item1 = $this->createMenuItem('Item 1', 5, $this->taxCategory);
        $item1->setSection($this->menuSection);

        $this->expectException(UnavailableProductException::class);
        $this->expectExceptionMessage(sprintf('Product %s is not available', $item1->getId()));

        $item1->setIsAvailable(false);
        $cart->addItem($item1, 1);
    }

    public function testRestaurantMismatch()
    {
        $restaurant = new Restaurant();
        $restaurant->setId(2);
        $restaurant->setName('Test restaurant');

        $cart = new Cart($restaurant);
        $item1 = $this->createMenuItem('Item 1', 5, $this->taxCategory);
        $item1->setSection($this->menuSection);

        $this->expectException(RestaurantMismatchException::class);
        $this->expectExceptionMessage(sprintf('Product %s doesn\'t belong to restaurant %s', $item1->getId(), $restaurant->getId()));

        $cart->addItem($item1, 1);
    }
}
