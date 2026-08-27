<?php

namespace AppBundle\MessageHandler\Zelty;

use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Integration\Zelty\ZeltyClient;
use AppBundle\Message\Zelty\PushOrder;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class PushOrderHandler
{
    public function __construct(
        private readonly ZeltyClient $zeltyClient,
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(PushOrder $message): void
    {
        $order = $this->orderRepository->find($message->getOrderId());

        if ($order === null) {
            return;
        }

        $restaurant = $order->getRestaurant();

        // Checked again here: the switch may have been flipped while the message was queued.
        if ($restaurant === null || !$restaurant->isZeltyOrdersEnabled()) {
            return;
        }

        $this->zeltyClient->setRestaurant($restaurant);
        $zeltyOrderId = $this->zeltyClient->pushToZelty($order);
        $total = $order->getItemsTotal() + $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT);
        $this->zeltyClient->addTransaction($zeltyOrderId, $total, $restaurant->getZeltyTransactionMethod());

        $order->setZeltyOrderId($zeltyOrderId);
        $this->entityManager->flush();
    }
}
