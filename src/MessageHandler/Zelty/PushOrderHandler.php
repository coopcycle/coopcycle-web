<?php

namespace AppBundle\MessageHandler\Zelty;

use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Integration\Zelty\ZeltyClient;
use AppBundle\Message\Zelty\PushOrder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PushOrderHandler
{
    public function __construct(
        private readonly ZeltyClient $zeltyClient,
        private readonly OrderRepository $orderRepository,
    ) {}

    public function __invoke(PushOrder $message): void
    {
        $order = $this->orderRepository->find($message->getOrderId());

        if ($order === null) {
            return;
        }

        $restaurant = $order->getRestaurant();

        $this->zeltyClient->setAuth($restaurant->getZeltyApiKey());
        $this->zeltyClient->pushToZelty($order);
    }
}
