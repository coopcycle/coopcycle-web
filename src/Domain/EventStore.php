<?php

namespace AppBundle\Domain;

use AppBundle\Domain\Order\Event as OrderDomainEvent;
use AppBundle\Domain\Task\Event as TaskDomainEvent;
use AppBundle\Entity\Sylius\OrderEvent;
use AppBundle\Entity\TaskEvent;
use AppBundle\Service\RequestContext;
use Doctrine\Common\Collections\ArrayCollection;

class EventStore extends ArrayCollection
{
    public function __construct(
        private readonly RequestContext $requestContext
    ) {
        parent::__construct([]);
    }

    public function createEvent(DomainEvent $event)
    {
        if ($event instanceof OrderDomainEvent) {
            return $this->createOrderEvent($event);
        }

        if ($event instanceof TaskDomainEvent) {
            return $this->createTaskEvent($event);
        }
    }

    public function addEvent(DomainEvent $event)
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

        if ($clientIp = $this->requestContext->getClientIp()) {
            $metadata['client_ip'] = $clientIp;
        }

        if ($userAgent = $this->requestContext->getUserAgent()) {
            $metadata['user_agent'] = $userAgent;
        } else {
            $metadata['user_agent'] = 'unknown';
        }

        if ($route = $this->requestContext->getRoute()) {
            $metadata['route'] = $route;
        }

        if ($username = $this->requestContext->getUsername()) {
            $metadata['username'] = $username;
        }

        $roles = $this->requestContext->getRoles();

        $metadata['roles'] = $roles;
        $metadata['roles_category'] = $this->getRolesCategory($roles);

        return $metadata;
    }

    private function getRolesCategory(array $roles): ?string
    {
        // Define roles priority for searching
        $rolesPriority = [
            'ROLE_ADMIN',
            'ROLE_DISPATCHER',
            'ROLE_COURIER',
            'ROLE_RESTAURANT',
            'ROLE_STORE',
        ];

        foreach ($rolesPriority as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        if (count($roles) > 0) {
            return $roles[0];
        } else {
            return 'ROLE_UNKNOWN';
        }
    }
}
