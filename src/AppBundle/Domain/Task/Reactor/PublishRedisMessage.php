<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event as TaskEvent;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Message\Event as EventMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

class PublishRedisMessage
{
    private $serializer;
    private $messageBus;

    public function __construct(SerializerInterface $serializer, MessageBusInterface $messageBus)
    {
        $this->serializer = $serializer;
        $this->messageBus = $messageBus;
    }

    public function __invoke(TaskEvent $event)
    {
        $data = [];
        if ($event instanceof SerializableEventInterface) {
            $data = $event->normalize($this->serializer);
        }

        $this->messageBus->dispatch(
            new EventMessage($event::messageName(), $data)
        );
    }
}
