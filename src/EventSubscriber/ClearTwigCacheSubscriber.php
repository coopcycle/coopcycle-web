<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Twig\CacheExtension\KeyGenerator;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class ClearTwigCacheSubscriber implements EventSubscriberInterface
{
    private $keyGenerator;
    private $twigCache;
    private $annotation;

    public function __construct(
        KeyGenerator $keyGenerator,
        CacheItemPoolInterface $twigCache,
        string $annotation)
    {
        $this->keyGenerator = $keyGenerator;
        $this->twigCache = $twigCache;
        $this->annotation = $annotation;
    }

    public function onCatalogUpdated(GenericEvent $event)
    {
        $cacheKey = $this->keyGenerator->generateKey($event->getSubject(), $this->annotation);

        $this->twigCache->deleteItem($cacheKey);
    }

    public static function getSubscribedEvents()
    {
        return array(
            'catalog.updated' => 'onCatalogUpdated',
        );
    }
}
