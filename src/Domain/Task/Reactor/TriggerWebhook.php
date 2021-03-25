<?php

namespace AppBundle\Domain\Task\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Task\Event;
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
        $task = $event->getTask();

        if (null === $task->getDelivery()) {
            return;
        }

        $this->messageBus->dispatch(
            new Webhook(
                $this->iriConverter->getIriFromItem($task->getDelivery()),
                $this->getEventName($event)
            )
        );
    }

    private function getEventName(Event $event)
    {
        $task = $event->getTask();

        if ($event instanceof Event\TaskAssigned && $task->isDropoff()) {
            return 'delivery.assigned';
        }

        if ($event instanceof Event\TaskStarted && $task->isPickup()) {
            return 'delivery.started';
        }

        if ($event instanceof Event\TaskFailed) {
            return 'delivery.failed';
        }

        if ($event instanceof Event\TaskDone) {
            return $task->isPickup() ? 'delivery.picked' : 'delivery.completed';
        }

        return '';
    }
}

