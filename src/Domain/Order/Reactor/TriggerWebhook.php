<?php

namespace AppBundle\Domain\Order\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Message\Webhook;
use Symfony\Component\Messenger\MessageBusInterface;

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

    public function __invoke(Event $event)
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

