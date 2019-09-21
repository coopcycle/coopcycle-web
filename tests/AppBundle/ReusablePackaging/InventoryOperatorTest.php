<?php

namespace Tests\AppBundle\ReusablePackaging;

use AppBundle\ReusablePackaging\InventoryOperator;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\ReusablePackaging;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class InventoryOperatorTest extends TestCase
{
    private $inventoryOperator;

    public function setUp(): void
    {
        $this->inventoryOperator = new InventoryOperator();
    }

    public function testHold()
    {
        $reusablePackaging = new ReusablePackaging();
        $reusablePackaging->setTracked(true);
        $reusablePackaging->setOnHand(10);

        $restaurant = $this->prophesize(Restaurant::class);
        $restaurant
            ->getReusablePackagings()
            ->willReturn([ $reusablePackaging]);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $order
            ->countReusablePackagingUnits()
            ->willReturn(2);

        $this->inventoryOperator->hold($order->reveal());

        $this->assertEquals(2, $reusablePackaging->getOnHold());
        $this->assertEquals(10, $reusablePackaging->getOnHand());
    }

    public function testRelease()
    {
        $reusablePackaging = new ReusablePackaging();
        $reusablePackaging->setTracked(true);
        $reusablePackaging->setOnHold(2);
        $reusablePackaging->setOnHand(10);

        $restaurant = $this->prophesize(Restaurant::class);
        $restaurant
            ->getReusablePackagings()
            ->willReturn([ $reusablePackaging]);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant->reveal());
        $order
            ->countReusablePackagingUnits()
            ->willReturn(2);

        $this->inventoryOperator->release($order->reveal());

        $this->assertEquals(0, $reusablePackaging->getOnHold());
        $this->assertEquals(10, $reusablePackaging->getOnHand());
    }
}
