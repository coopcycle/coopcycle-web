<?php

namespace AppBundle\Domain\Task\Reactor;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Task;
use AppBundle\Domain\Task\Event;
use AppBundle\Service\SocketIoManager;
use Predis\Client as Redis;

use Symfony\Component\Serializer\SerializerInterface;

class PublishRedisMessage
{
    private $serializer;
    private $socketIoManager;

    public function __construct(
        SerializerInterface $serializer,
        SocketIoManager $socketIoManager)
    {
        $this->serializer = $serializer;
        $this->socketIoManager = $socketIoManager;
    }

    public function __invoke(Event $event)
    {
        try {
            $serializedTask = $this->serializer->normalize($event->getTask(), 'jsonld', [
                'resource_class' => Task::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task', 'delivery', 'place']
            ]);

            $this->socketIoManager->toAdmins($event::messageName(), ['task' => $serializedTask]);
        } catch (InvalidArgumentException $e) {

        }

    }
}
