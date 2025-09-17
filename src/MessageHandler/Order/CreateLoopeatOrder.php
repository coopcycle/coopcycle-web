<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\LoopEat\Client as LoopEatClient;
use GuzzleHttp\Exception\RequestException;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: -20)]
class CreateLoopeatOrder
{
    public function __construct(
        private LoopEatClient $client,
        private OrderProcessorInterface $orderProcessor)
    {}

	public function __invoke(OrderCreated $event)
    {
        $order = $event->getOrder();

        if (!$order->isLoopeat()) {
            return;
        }

        try {

            $result = $this->client->createOrder($order);
            $order->setLoopeatOrderId($result['id']);

        } catch (RequestException $e) {

            // Remove zero waste
            $order->setReusablePackagingEnabled(false);
            $order->setLoopeatReturns([]);

            $this->orderProcessor->process($order);
        }
    }
}
