<?php

namespace AppBundle\Fixtures\AliceDataFixtures;

use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\UsePricingRules;
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

        $productVariants = $this->pricingManager->getProductVariantsWithPricingStrategy(
            $delivery,
            new UsePricingRules()
        );

        // when a store does not have pricing rules
        // randomly: keep the price 0 or use an arbitrary price
        if (1 === count($productVariants) && 0 === $productVariants[0]->getOptionValuesPrice() && random_int(0, 1) === 0) {
            $price = new ArbitraryPrice(random_int(0, 1) === 0 ? null : 'Arbitrary name', random_int(500, 20000));
            $productVariants = [$this->pricingManager->getCustomProductVariant($delivery, $price)];
        }

        $this->pricingManager->processDeliveryOrder(
            $order,
            $productVariants
        );

        // Changes are flushed inside FeatureContext
        // Flushing here makes tests too slow
    }
}
