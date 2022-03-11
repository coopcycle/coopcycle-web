<?php

namespace AppBundle\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @see https://symfony.com/doc/4.4/translation/locale.html
 * @see https://symfony.com/doc/4.4/session/locale_sticky_session.html
 */
class LocaleListener implements EventSubscriberInterface
{
    private $defaultLocale;

    private static $blacklist = [
        'restaurant_fulfillment_timing',
        'fos_js_routing_js',
        'search_geocode',
    ];

    public function __construct($defaultLocale = 'en')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        // Do not start a session to avoid locking concurrent AJAX requests
        // https://tideways.com/profiler/blog/slow-ajax-requests-in-your-symfony-application-apply-this-simple-fix
        if ($request->attributes->has('_route') && in_array($request->attributes->get('_route'), self::$blacklist)) {
            return;
        }

        if (!$request->hasPreviousSession()) {
            return;
        }

        // try to see if the locale has been set as a _locale routing parameter
        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        } elseif ($locale = $request->query->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        }

        $locale = $request->getSession()->get('_locale', $this->defaultLocale);
        $request->attributes->set('_locale', $locale);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            // must be registered BEFORE the default Locale listener
            KernelEvents::REQUEST => array(array('onKernelRequest', 17)),
        );
    }
}
