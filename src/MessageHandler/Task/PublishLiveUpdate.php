<?php

namespace AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event as TaskEvent;
use AppBundle\Message\Task\PublishLiveUpdate as PublishLiveUpdateMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class PublishLiveUpdate
{
    public function __construct(private MessageBusInterface $messageBus)
    {}

    public function __invoke(TaskEvent $event)
    {
        $this->messageBus->dispatch(
            new PublishLiveUpdateMessage($event->getTask()->getId(), get_class($event))
        );
    }
}
