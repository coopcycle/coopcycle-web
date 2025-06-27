<?php

namespace AppBundle\Session;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorageFactory as BaseNativeSessionStorageFactory;

// Help opcache.preload discover always-needed symbols
class_exists(NativeSessionStorage::class);

class NativeSessionStorageFactory implements SessionStorageFactoryInterface
{
    public function __construct(private BaseNativeSessionStorageFactory $factory)
    {}

    public function createStorage(?Request $request): SessionStorageInterface
    {
        /** @var NativeSessionStorage $storage */
        $storage = $this->factory->createStorage($request);

        // Make sure to set a default value for cookie_samesite
        $storage->setOptions([
            'cookie_samesite' => Cookie::SAMESITE_LAX,
        ]);

        $hasEmbedParam = $request->query->has('embed')
            && ('' === $request->query->get('embed') || true === $request->query->getBoolean('embed'));

        if ($hasEmbedParam) {
            // @see Symfony\Component\HttpKernel\EventListener\AbstractSessionListener
            $storage->setOptions([
                // We also change the name of the session cookie,
                // to make sure it will not be recycled
                'name' => sprintf('%s_EMBED', $storage->getName()),
                'cookie_samesite' => Cookie::SAMESITE_NONE,
                'cookie_secure' => true,
            ]);
        }

        return $storage;
    }
}
