<?php

namespace AppBundle\EventSubscriber;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Twig\CacheExtension\Extension as TwigCacheExtension;

class ClearTwigCacheSubscriber implements EventSubscriberInterface
{
    private $twigCacheExtension;
    private $twigCache;
    private $annotation;

    public function __construct(
        TwigCacheExtension $twigCacheExtension,
        CacheItemPoolInterface $twigCache,
        string $annotation)
    {
        $this->twigCacheExtension = $twigCacheExtension;
        $this->twigCache = $twigCache;
        $this->annotation = $annotation;
    }

    public function onCatalogUpdated(GenericEvent $event)
    {
        $cacheKey =
            $this->twigCacheExtension->getCacheStrategy()->generateKey($this->annotation, $event->getSubject());

        $this->twigCache->deleteItem($cacheKey);
    }

    public static function getSubscribedEvents()
    {
        return array(
            'catalog.updated' => 'onCatalogUpdated',
        );
    }
}
