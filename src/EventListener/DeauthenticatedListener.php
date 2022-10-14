<?php

namespace AppBundle\EventListener;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\DeauthenticatedEvent;

class DeauthenticatedListener
{
    private $requestStack;
    private $sessionKeyName;

    public function __construct(RequestStack $requestStack, string $sessionKeyName)
    {
        $this->requestStack = $requestStack;
        $this->sessionKeyName = $sessionKeyName;
    }

    public function logoutOnChange(DeauthenticatedEvent $event)
    {
        $this->requestStack->getSession()->remove($this->sessionKeyName);
    }
}
