<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use Predis\Client as Redis;
use Symfony\Component\Serializer\SerializerInterface;

class PublishToRedis
{
    private $redis;
    private $serializer;

    public function __construct(Redis $redis, SerializerInterface $serializer)
    {
        $this->redis = $redis;
        $this->serializer = $serializer;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();

        $channel = sprintf('order:%d:events', $order->getId());

        $message = [
            'name' => $event::messageName(),
            'data' => $event->toPayload(),
            // FIXME We should retrieve the actual date from EventStore
            'createdAt' => (new \DateTime())->format(\DateTime::ATOM),
        ];

        $this->redis->publish($channel, json_encode($message));

        if ($event instanceof Event\OrderCreated && $order->isFoodtech()) {
            $this->redis->publish(
                sprintf('restaurant:%d:orders', $order->getRestaurant()->getId()),
                $this->serializer->serialize($order, 'jsonld', ['groups' => ['order']])
            );
        }
    }
}
