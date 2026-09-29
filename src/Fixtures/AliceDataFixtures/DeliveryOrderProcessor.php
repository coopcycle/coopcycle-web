<?php

namespace AppBundle\Fixtures\AliceDataFixtures;

use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Sylius\Order\OrderInterface as BaseOrderInterface;
use Fidry\AliceDataFixtures\ProcessorInterface;
use Sylius\Component\Order\Model\OrderInterface;

final class DeliveryOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DeliveryManager $deliveryManager,
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

        $this->deliveryManager->setDefaults($delivery);

        // Skip orders without a store (B2C clients)
        if (null === $delivery->getStore()) {
            return;
        }

        $productVariants = $this->pricingManager->getProductVariantsWithPricingStrategy(
            $delivery,
            new CalculateUsingPricingRules()
        );

        // when a store does not have pricing rules
        // randomly: keep the price 0 or use an arbitrary price
        if (1 === count($productVariants) && 0 === $productVariants[0]->getOptionValuesPrice() && random_int(0, 1) === 0) {
            $price = new ArbitraryPrice(random_int(0, 1) === 0 ? null : 'Arbitrary name', random_int(500, 20000));
            $productVariants = [$this->pricingManager->getCustomProductVariant($delivery, $price)];
        }

        // The Sylius order processors -- OrderPaymentProcessor in particular --
        // only run while the order is still in cart/new/accepted
        // (@see \AppBundle\Sylius\OrderProcessing\OrderPaymentProcessor).
        // Alice runs a fixture's __calls during instantiation, so a fixture
        // that declares a post-checkout state (the invoicing tests need
        // 'fulfilled' orders) reaches this point already fulfilled, and would
        // end up with no payment at all -- which no real order ever has, and
        // which makes Order::getPaymentMethod() return an empty string.
        //
        // Process the order the way it was actually built, then restore the
        // state the fixture asked for.
        $declaredState = $order->getState();

        $isProcessable = in_array($declaredState, [
            OrderInterface::STATE_CART,
            OrderInterface::STATE_NEW,
            BaseOrderInterface::STATE_ACCEPTED,
        ], true);

        if (!$isProcessable) {
            $order->setState(OrderInterface::STATE_NEW);
        }

        $this->pricingManager->processDeliveryOrder(
            $order,
            $productVariants
        );

        if (!$isProcessable) {
            $order->setState($declaredState);
        }

        // Changes are flushed inside FeatureContext
        // Flushing here makes tests too slow
    }
}
