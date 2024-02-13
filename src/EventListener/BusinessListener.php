<?php

declare(strict_types=1);

namespace AppBundle\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class BusinessListener
{
    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$event->getRequest()->query->has('_business')) {
            return;
        }

        $isBusiness = $event->getRequest()->query->getBoolean('_business');
        $response = $event->getResponse();

        if ($isBusiness) {
            $response->headers->setCookie(new Cookie('_coopcycle_business', '1'));
        } else {
            $response->headers->clearCookie('_coopcycle_business');
        }
    }
}
