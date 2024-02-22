<?php

namespace AppBundle\EventListener;

use AppBundle\Sylius\Cart\SessionStorage;
use Symfony\Component\Security\Http\Event\DeauthenticatedEvent;

class DeauthenticatedListener
{
    public function __construct(private SessionStorage $storage)
    {}

    public function logoutOnChange(DeauthenticatedEvent $event)
    {
        $this->storage->remove();
    }
}
