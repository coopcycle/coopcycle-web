<?php

namespace AppBundle\EventListener;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\Event\DeauthenticatedEvent;

class DeauthenticatedListener
{
    private $session;
    private $sessionKeyName;

    public function __construct(SessionInterface $session, string $sessionKeyName)
    {
        $this->session = $session;
        $this->sessionKeyName = $sessionKeyName;
    }

    public function logoutOnChange(DeauthenticatedEvent $event)
    {
        $this->session->remove($this->sessionKeyName);
    }
}
