<?php

namespace AppBundle\Fixtures\AliceDataFixtures;

use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Pricing\PricingManager;
use Fidry\AliceDataFixtures\ProcessorInterface;

final class DeliveryOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly PricingManager $pricingManager,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function preProcess(string $id, $object): void
    {
        // do nothing
    }

    /**
     * @inheritdoc
     */
    public function postProcess(string $id, $object): void
    {
        if (!$object instanceof Order) {
            return;
        }

        $order = $object;

        // Only process delivery orders (package delivery orders), not foodtech orders
        if ($order->isFoodtech()) {
            return;
        }

        $delivery = $order->getDelivery();
        if (null === $delivery) {
            return;
        }

        $price = new ArbitraryPrice(null, random_int(500, 20000));
        $this->pricingManager->processDeliveryOrder(
            $order,
            [$this->pricingManager->getCustomProductVariant($delivery, $price)]
        );

        // Changes are flushed inside FeatureContext
        // Flushing here makes tests too slow
    }
}
