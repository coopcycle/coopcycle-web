<?php

namespace Tests\AppBundle\EventListener;

use AppBundle\Entity\Hub;
use AppBundle\Entity\HubRepository;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Vendor;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Event\ItemAddedEvent;
use AppBundle\EventListener\CheckoutListener;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class CheckoutListenerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->hubRepository = $this->prophesize(HubRepository::class);
        $this->localBusinessRepository = $this->prophesize(LocalBusinessRepository::class);

        $this->listener = new CheckoutListener(
            $this->hubRepository->reveal(),
            $this->localBusinessRepository->reveal()
        );
    }

    private function createItem(ProductInterface $product)
    {
        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getProduct()->willReturn($product);

        $item = $this->prophesize(OrderItemInterface::class);
        $item->getVariant()->willReturn($variant->reveal());

        return $item->reveal();
    }

    public function testUpgradeVendor()
    {
        $restaurant = $this->prophesize(LocalBusiness::class);
        $otherRestaurant = $this->prophesize(LocalBusiness::class);

        $vendor = new Vendor();
        $vendor->setRestaurant($restaurant->reveal());

        $product1 = $this->prophesize(ProductInterface::class);
        $product2 = $this->prophesize(ProductInterface::class);

        $this->localBusinessRepository
            ->findOneByProduct($product1->reveal())
            ->willReturn($restaurant->reveal());

        $this->localBusinessRepository
            ->findOneByProduct($product2->reveal())
            ->willReturn($otherRestaurant->reveal());

        $cart = $this->prophesize(Order::class);
        $cart
            ->getVendor()
            ->willReturn($vendor);
        $cart
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createItem($product1->reveal()),
                $this->createItem($product2->reveal())
            ]));

        $hub = new Hub();

        $this->hubRepository
            ->findOneByRestaurant($restaurant->reveal())
            ->willReturn($hub);

        $this->hubRepository
            ->findOneByRestaurant($otherRestaurant->reveal())
            ->willReturn($hub);

        $this->listener->onItemAdded(new ItemAddedEvent($cart->reveal()));

        $cart->setVendor(Argument::that(function (Vendor $vendor) use ($hub) {
            return $vendor->getHub() === $hub;
        }))->shouldHaveBeenCalled();
    }
}

