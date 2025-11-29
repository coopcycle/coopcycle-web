<?php

namespace AppBundle\Domain;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Domain\Order\Event as OrderDomainEvent;
use AppBundle\Domain\Task\Event as TaskDomainEvent;
use AppBundle\Domain\DomainEvent;
use AppBundle\Entity\Sylius\OrderEvent;
use AppBundle\Entity\TaskEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $metadata['client_ip'] = $request->getClientIp();

            if ($request->headers->has('User-Agent')) {
                $metadata['user_agent'] = $request->headers->get('User-Agent');
            } else {
                $metadata['user_agent'] = 'unknown';
            }

            $metadata['route'] = $request->attributes->get('_route');
        }

        $user = $this->getUser();
        if ($user) {
            $metadata['username'] = $user->getUsername();
            $metadata['role'] = $this->getRole($user);
        }

        return $metadata;
    }

    private function getRole(UserInterface $user): ?string
    {
        // Define roles priority for searching
        $rolesPriority = [
            'ROLE_ADMIN',
            'ROLE_DISPATCHER',
            'ROLE_COURIER',
            'ROLE_RESTAURANT',
            'ROLE_STORE',
        ];

        $userRoles = $user->getRoles();

        foreach ($rolesPriority as $role) {
            if (in_array($role, $userRoles, true)) {
                return $role;
            }
        }

        if (count($userRoles) > 0) {
            return $userRoles[0];
        } else {
            return 'ROLE_USER';
        }
    }
}
