<?php

namespace AppBundle\Fixtures\AliceDataFixtures;

use AppBundle\Entity\Sylius\Order;
use Fidry\AliceDataFixtures\ProcessorInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Adds one line item (with a random quantity) per product carried by the
 * restaurant to every foodtech order created in fixtures, then runs the
 * order through the real order processor so items/adjustments/taxes end up
 * computed exactly like a real cart (see CartItemProcessor).
 */
final class FoodtechOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly FactoryInterface $orderItemFactory,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly OrderProcessorInterface $orderProcessor,
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

        // Only process foodtech orders (restaurant orders), not on-demand delivery orders
        if (!$order->isFoodtech()) {
            return;
        }

        // Already has items (e.g. processed before, or set explicitly in fixtures)
        if (count($order->getItems()) > 0) {
            return;
        }

        $restaurant = $order->getRestaurant();
        if (null === $restaurant) {
            // Multi-vendor order, not supported here
            return;
        }

        foreach ($restaurant->getProducts() as $product) {
            $variant = $product->getVariants()->first();
            if (false === $variant) {
                continue;
            }

            $orderItem = $this->orderItemFactory->createNew();
            $orderItem->setVariant($variant);
            $orderItem->setUnitPrice($variant->getPrice());

            $this->orderItemQuantityModifier->modify($orderItem, random_int(1, 3));

            $this->orderModifier->addToOrder($order, $orderItem);
        }

        if ($order->isEmpty()) {
            return;
        }

        $this->orderProcessor->process($order);

        // Changes are flushed inside FeatureContext
        // Flushing here makes tests too slow
    }
}
