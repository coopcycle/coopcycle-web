<?php

namespace AppBundle\EventListener;

use AppBundle\Business\Context as BusinessContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private BusinessContext $businessContext
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => 'onLogout'];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $response = $event->getResponse();

        /**
         * If loggedout user was in business mode
         * remove the cookie to prevent filter shops/products for anonymous or future loggedin users
         */
        if ($this->businessContext->isActive()) {
            $response->headers->clearCookie('_coopcycle_business');
        }

    }
}
