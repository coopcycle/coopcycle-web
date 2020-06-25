<?php

namespace AppBundle\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

class AjaxSessionCloseListener
{
    protected static $whitelist = [
        'profile_notifications',
        'profile_jwt',
    ];

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->isXmlHttpRequest()) {
            return;
        }

        if (!$request->attributes->has('_route')) {
            return;
        }

        $route = $request->attributes->get('_route');

        if (!in_array($route, self::$whitelist)) {
            return;
        }

        $session = $request->getSession();
        $session->save();
    }
}
