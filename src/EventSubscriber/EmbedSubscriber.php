<?php

namespace AppBundle\EventSubscriber;

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

        $hasEmbedParam = $request->query->has('embed')
            && ('' === $request->query->get('embed') || true === $request->query->getBoolean('embed'));

        if ($hasEmbedParam) {
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

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                // Run *BEFORE* Symfony\Component\HttpKernel\EventListener\SessionListener (priority = 128)
                ['setCookieSameSiteNoneSecure', 132],
            ],
        ];
    }
}
