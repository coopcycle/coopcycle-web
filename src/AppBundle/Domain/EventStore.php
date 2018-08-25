<?php

namespace AppBundle\Domain;

use AppBundle\Domain\Order\Event as OrderDomainEvent;
use AppBundle\Entity\Sylius\OrderEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EventStore
{
    private $tokenStorage;
    private $requestStack;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack)
    {
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
    }

    public function add(Event $event)
    {
        if ($event instanceof OrderDomainEvent) {
            $order = $event->getOrder();
            $order->addEvent($this->createOrderEvent($event));
        }
    }

    private function createOrderEvent(Event $event)
    {
        $orderEvent = new OrderEvent();

        $orderEvent->setType($event::messageName());
        $orderEvent->setOrder($event->getOrder());
        $orderEvent->setData($event->toPayload());

        $metadata = [];

        $request = $this->requestStack->getCurrentRequest();

        if ($request) {
            $metadata['client_ip'] = $request->getClientIp();
        }

        $orderEvent->setMetadata($metadata);

        return $orderEvent;
    }
}
