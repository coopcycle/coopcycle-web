<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Controller\EmbedController;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EmbedSubscriber implements EventSubscriberInterface
{
    private $storage;
    private $debug;

    public function __construct(SessionStorageInterface $storage, bool $debug)
    {
        $this->storage = $storage;
        $this->debug = $debug;
    }

    /**
     * @see https://web.dev/samesite-cookies-explained/
     * @see https://web.dev/samesite-cookie-recipes/
     */
    public function setCookieSameSiteNoneSecure(RequestEvent $event)
    {
        if ($this->debug) {
            return;
        }

        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->attributes->has('_controller')) {
            return;
        }

        $controller = $request->attributes->get('_controller');

        [$class, $method] = explode('::', $controller, 2);

        if ($request->query->has('embed') || $class === EmbedController::class) {
            // @see Symfony\Component\HttpKernel\EventListener\SessionListener
            if ($this->storage instanceof NativeSessionStorage) {
                $this->storage->setOptions([
                    'cookie_samesite' => Cookie::SAMESITE_NONE,
                    'cookie_secure' => true,
                ]);
            }
        }
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        if ($request->query->has('embed')) {
            if ('' === $request->query->get('embed') || true === $request->query->getBoolean('embed')) {
                $request->getSession()->set('embed', true);
            } else {
                $request->getSession()->remove('embed');
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                // Run *AFTER* Symfony\Component\HttpKernel\EventListener\RouterListener (priority = 32)
                ['setCookieSameSiteNoneSecure', 24],
                ['onKernelRequest', 0],
            ],
        ];
    }
}
