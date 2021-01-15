<?php

namespace AppBundle\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @see https://tideways.com/profiler/blog/slow-ajax-requests-in-your-symfony-application-apply-this-simple-fix
 */
class AjaxSessionCloseListener
{
    protected static $whitelist = [
        'profile_notifications',
        'profile_jwt',
        'restaurant_fulfillment_timing',
        'fos_js_routing_js',
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

        if (!$request->hasPreviousSession() || !$request->getSession()->isStarted()) {
            return;
        }

        $session = $request->getSession();
        $session->save();
    }
}
