<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Task;
use AppBundle\Domain\Task\Event;
use Predis\Client as Redis;
use Symfony\Component\Serializer\SerializerInterface;

class PublishRedisMessage
{
    private $serializer;
    private $redis;

    public function __construct(SerializerInterface $serializer, Redis $redis)
    {
        $this->serializer = $serializer;
        $this->redis = $redis;
    }

    public function __invoke(Event $event)
    {
        $serializedTask = $this->serializer->normalize($event->getTask(), 'jsonld', [
            'resource_class' => Task::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task', 'delivery', 'place']
        ]);

        $serializedUser = null;
        if (is_callable([$event, 'getUser'])) {
            $serializedUser = $this->serializer->normalize($event->getUser(), 'jsonld', [
                'resource_class' => ApiUser::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get'
            ]);
        }

        $this->redis->publish(
            $event::messageName(),
            json_encode([
                'task' => $serializedTask,
                'user' => $serializedUser
            ])
        );
    }
}
