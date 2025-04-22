<?php

namespace AppBundle\MessageHandler\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Message\Webhook;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler()]
class TriggerWebhook
{
    private $messageBus;
    private $iriConverter;

    public function __construct(
        MessageBusInterface $messageBus,
        IriConverterInterface $iriConverter)
    {
        $this->messageBus = $messageBus;
        $this->iriConverter = $iriConverter;
    }

    public function __invoke(OrderCreated $event)
    {
        $order = $event->getOrder();

        if (null === $order->getRestaurant()) {
            return;
        }

        $this->messageBus->dispatch(
            new Webhook(
                $this->iriConverter->getIriFromItem($order),
                $this->getEventName($event)
            )
        );
    }

    private function getEventName(Event $event)
    {
        if ($event instanceof Event\OrderCreated) {
            return 'order.created';
        }

        return '';
    }
}

