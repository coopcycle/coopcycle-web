<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Service\SocketIoManager;
use Symfony\Component\Serializer\SerializerInterface;

class PublishToRedis
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
        $message = $this->createMessage($event);

        $this->socketIoManager->toAdmins($event::messageName(), $message);

        $customer = $event->getOrder()->getCustomer();
        if (null !== $customer) {
            $this->socketIoManager->toUser($customer, $event::messageName(), $message);
        }
    }

    private function createMessage(Event $event)
    {
        $order = $event->getOrder();

        return [
            'order' => $this->serializer->normalize($order, 'jsonld', ['groups' => ['order', 'address', 'place']]),
            'data' => $event->toPayload(),
            // FIXME We should retrieve the actual date from EventStore
            'createdAt' => (new \DateTime())->format(\DateTime::ATOM),
        ];
    }
}
