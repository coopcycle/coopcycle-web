<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Message\Zelty\PushOrder;
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

        try {
            $this->commandBus->dispatch(new PushOrder($order->getId()));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to dispatch Zelty push order', [
                'order_id' => $order->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
