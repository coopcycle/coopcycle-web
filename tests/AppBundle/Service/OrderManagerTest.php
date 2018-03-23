<?php

namespace AppBundle\Utils;

use AppBundle\BaseTest;
use AppBundle\Entity\Cart\Cart;
use AppBundle\Entity\Cart\CartItem;
use AppBundle\Entity\Contract;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\SettingsManager;

class OrderManagerTest extends BaseTest
{
    private $orderManager;
    private $taxCategoryImmediate;
    private $taxCategoryDeferred;

    public function setUp()
    {
        parent::setUp();

        $settingsManager = $this->prophesize(SettingsManager::class);
        $settingsManager
            ->get('default_tax_category')
            ->willReturn('tva_livraison');
        $settingsManager
            ->get('stripe_secret_key')
            ->willReturn('sk_test_123456789');

        static::$kernel->getContainer()->set('coopcycle.settings_manager', $settingsManager->reveal());

        $this->orderManager = static::$kernel->getContainer()->get('order.manager');

        $this->taxCategoryImmediate = $this->createTaxCategory('TVA consommation immédiate', 'tva_conso_immediate',
            'TVA 10%', 'tva_10', 0.10, 'float');
        $this->taxCategoryDeferred = $this->createTaxCategory('TVA consommation différée', 'tva_conso_differee',
            'TVA 5.5%', 'tva_5_5', 0.055, 'float');

        $this->createTaxCategory('TVA livraison', 'tva_livraison', 'TVA 20%', 'tva_20', 0.20, 'float');
    }

    public function testApplyTaxes()
    {
        // 5 - (5 / (1 + 0.10)) = 0.45
        $item1 = $this->createMenuItem('Item 1', 5.00, $this->taxCategoryImmediate);
        // 10 - (10 / (1 + 0.055)) = 0.52
        $item2 = $this->createMenuItem('Item 2', 10.00, $this->taxCategoryDeferred);

        $contract = new Contract();
        // 3.5 - (3.5 / (1 + 0.20)) = 0.58
        $contract->setFlatDeliveryPrice(3.5);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $delivery = new Delivery();
        $delivery->setDate(new \DateTime('today 12:30:00'));
        $delivery->setDuration(30);
        $delivery->setDistance(1500);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->setDelivery($delivery);

        $cart = new Cart();

        $order->addCartItem(new CartItem($cart, $item1, 1), $item1);
        $order->addCartItem(new CartItem($cart, $item2, 1), $item2);

        $this->orderManager->applyTaxes($order);

        $this->assertEquals(02.92, $delivery->getTotalExcludingTax());
        $this->assertEquals(00.58, $delivery->getTotalTax());
        $this->assertEquals(03.50, $delivery->getTotalIncludingTax());

        $this->assertEquals(14.03, $order->getTotalExcludingTax());
        $this->assertEquals(00.97, $order->getTotalTax());
        $this->assertEquals(15.00, $order->getTotalIncludingTax());
    }
}
