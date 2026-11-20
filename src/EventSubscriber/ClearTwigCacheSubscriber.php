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
    private $locales;

    public function __construct(
        KeyGenerator $keyGenerator,
        CacheItemPoolInterface $twigCache,
        string $annotation,
        string $localeRegex)
    {
        $this->keyGenerator = $keyGenerator;
        $this->twigCache = $twigCache;
        $this->annotation = $annotation;
        $this->locales = explode('|', $localeRegex);
    }

    public function onCatalogUpdated(GenericEvent $event)
    {
        // The menu is cached per locale & business context, see templates/restaurant/index.html.twig
        $cacheKeys = [];
        foreach ($this->locales as $locale) {
            foreach (['', '_business'] as $suffix) {
                $cacheKeys[] = $this->keyGenerator->generateKey($event->getSubject(), $this->annotation . '_' . $locale . $suffix);
            }
        }

        $this->twigCache->deleteItems($cacheKeys);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'catalog.updated' => 'onCatalogUpdated',
        );
    }
}
