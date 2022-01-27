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

    public function __construct(SessionStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @see https://web.dev/samesite-cookies-explained/
     * @see https://web.dev/samesite-cookie-recipes/
     */
    public function setCookieSameSiteNoneSecure(RequestEvent $event)
    {
        // Make sure to set a default value for cookie_samesite
        if ($this->storage instanceof NativeSessionStorage) {
            $this->storage->setOptions([
                'cookie_samesite' => Cookie::SAMESITE_LAX,
            ]);
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->attributes->has('_controller')) {
            return;
        }

        $controller = $request->attributes->get('_controller');

        [$class] = explode('::', $controller, 2);

        if ($request->query->has('embed') || $class === EmbedController::class) {
            // @see Symfony\Component\HttpKernel\EventListener\AbstractSessionListener
            if ($this->storage instanceof NativeSessionStorage) {
                $this->storage->setOptions([
                    // We also change the name of the session cookie,
                    // to make sure it will not be recycled
                    'name' => sprintf('%s_EMBED', $this->storage->getName()),
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
