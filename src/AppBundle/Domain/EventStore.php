<?php

namespace AppBundle\Domain;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Domain\Order\Event as OrderDomainEvent;
use AppBundle\Domain\Task\Event as TaskDomainEvent;
use AppBundle\Entity\Sylius\OrderEvent;
use AppBundle\Entity\TaskEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EventStore extends ArrayCollection
{
    use TokenStorageTrait;

    private $requestStack;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack)
    {
        parent::__construct([]);

        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
    }

    public function createEvent(Event $event)
    {
        if ($event instanceof OrderDomainEvent) {
            return $this->createOrderEvent($event);
        }

        if ($event instanceof TaskDomainEvent) {
            return $this->createTaskEvent($event);
        }
    }

    public function addEvent(Event $event)
    {
        if ($event instanceof OrderDomainEvent) {
            $domainEvent = $this->createOrderEvent($event);

            $this->add($domainEvent);

            $event->getOrder()
                ->getEvents()
                ->add($domainEvent);
        }

        if ($event instanceof TaskDomainEvent) {
            $domainEvent = $this->createTaskEvent($event);

            $this->add($domainEvent);

            $event->getTask()->addEvent($domainEvent);
        }
    }

    private function createOrderEvent(OrderDomainEvent $event)
    {
        return new OrderEvent(
            $event->getOrder(),
            $event::messageName(),
            $event->toPayload(),
            $this->getMetadata(),
            new \DateTime()
        );
    }

    private function createTaskEvent(TaskDomainEvent $event)
    {
        return new TaskEvent(
            $event->getTask(),
            $event::messageName(),
            $event->toPayload(),
            $this->getMetadata(),
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

        $user = $this->getUser();
        if ($user) {
            $metadata['username'] = $user->getUsername();
        }

        return $metadata;
    }
}
