<?php

namespace AppBundle\Domain;

use AppBundle\Domain\Order\Event as OrderDomainEvent;
use AppBundle\Domain\Task\Event as TaskDomainEvent;
use AppBundle\Entity\Sylius\OrderEvent;
use AppBundle\Entity\TaskEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EventStore extends ArrayCollection
{
    private $tokenStorage;
    private $requestStack;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack)
    {
        parent::__construct([]);

        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
    }

    public function addEvent(Event $event)
    {
        if ($event instanceof OrderDomainEvent) {
            $this->add($this->createOrderEvent($event));
        }

        if ($event instanceof TaskDomainEvent) {
            $this->add($this->createTaskEvent($event));
        }
    }

    private function createOrderEvent(Event $event)
    {
        return new OrderEvent(
            $event->getOrder(),
            $event::messageName(),
            $event->toPayload(),
            $this->getMetadata(),
            new \DateTime()
        );
    }

    private function createTaskEvent(Event $event)
    {
        $data = $event->toPayload();
        $metadata = $this->getMetadata();

        return new TaskEvent(
            $event->getTask(),
            $event::messageName(),
            $data,
            $metadata,
            new \DateTime()
        );
    }

    private function getMetadata()
    {
        $metadata = [];

        $request = $this->requestStack->getCurrentRequest();

        if ($request) {
            $metadata['client_ip'] = $request->getClientIp();
        }

        return $metadata;
    }
}
