<?php

namespace AppBundle\MessageHandler\GiftCard;

use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Message\GiftCard\SettleGiftCardOrder;
use AppBundle\Service\GiftCard\GiftCardSettlement;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class SettleGiftCardOrderHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly GiftCardSettlement $giftCardSettlement,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SettleGiftCardOrder $message): void
    {
        $order = $this->orderRepository->find($message->getOrderId());

        if (null === $order) {
            $this->logger->error(sprintf('Order #%d not found, cannot settle', $message->getOrderId()));

            return;
        }

        $this->giftCardSettlement->settle(
            $order,
            $message->getComplementChargeId(),
            $message->getComplementCaptured()
        );
    }
}
