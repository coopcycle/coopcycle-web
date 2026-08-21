<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Message\Zelty\PushOrder;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DispatchZeltyPushOrder
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(OrderCreated $event): void
    {
        $order = $event->getOrder();
        $restaurant = $order->getRestaurant();

        if ($restaurant === null || empty($restaurant->getZeltyApiKey())) {
            return;
        }

        if (!$restaurant->isZeltyOrdersEnabled()) {
            $this->logger->info('Zelty order sync is disabled for this restaurant, skipping push', [
                'order_id'      => $order->getId(),
                'restaurant_id' => $restaurant->getId(),
            ]);

            return;
        }

        if (!$this->hasOnlyZeltyProducts($order)) {
            $this->logger->warning('Order was not sent to Zelty: it contains products that were not imported from Zelty', [
                'order_id'      => $order->getId(),
                'restaurant_id' => $restaurant->getId(),
            ]);

            return;
        }

        try {
            $this->commandBus->dispatch(new PushOrder($order->getId()));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to dispatch Zelty push order', [
                'order_id' => $order->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Zelty identifies every line of an order by the internal ID of the dish in its
     * own catalog. A product that was not imported from Zelty has no such ID, and the
     * whole order is rejected ("Cannot find dish", plus a total mismatch), so an order
     * is only worth sending when all of its items come from the Zelty catalog.
     */
    private function hasOnlyZeltyProducts(OrderInterface $order): bool
    {
        if (count($order->getItems()) === 0) {
            return false;
        }

        foreach ($order->getItems() as $item) {
            $product = $item->getVariant()?->getProduct();

            if ($product === null || empty($product->getZeltyInternalId())) {
                return false;
            }
        }

        return true;
    }
}
