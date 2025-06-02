<?php

namespace AppBundle\EventSubscriber;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorageFactory;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EmbedSubscriber implements EventSubscriberInterface
{
    private $factory;

    public function __construct(NativeSessionStorageFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @see https://web.dev/samesite-cookies-explained/
     * @see https://web.dev/samesite-cookie-recipes/
     */
    public function setCookieSameSiteNoneSecure(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $storage = $this->factory->createStorage($request);

        // Make sure to set a default value for cookie_samesite
        if ($storage instanceof NativeSessionStorage) {
            $storage->setOptions([
                'cookie_samesite' => Cookie::SAMESITE_LAX,
            ]);
        }

        $hasEmbedParam = $request->query->has('embed')
            && ('' === $request->query->get('embed') || true === $request->query->getBoolean('embed'));

        if ($hasEmbedParam) {
            // @see Symfony\Component\HttpKernel\EventListener\AbstractSessionListener
            if ($storage instanceof NativeSessionStorage) {
                $storage->setOptions([
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
