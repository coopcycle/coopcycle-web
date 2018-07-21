<?php

namespace AppBundle\EventListener;

use Sonata\SeoBundle\Seo\SeoPageInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\Translation\TranslatorInterface;

class SeoListener
{
    private $translator;
    private $seoPage;

    public function __construct(TranslatorInterface $translator, SeoPageInterface $seoPage)
    {
        $this->translator = $translator;
        $this->seoPage = $seoPage;
    }

    /**
     * Sets defaults that can be overriden by controllers.
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $locale = $request->getLocale();

        $this->seoPage
            ->addTitle($this->translator->trans('meta.title'));

        $this->seoPage
            ->setLinkCanonical($request->getUri());

        // @see http://ogp.me/#metadata
        $this->seoPage
            ->addMeta('property', 'og:title', $this->seoPage->getTitle())
            ->addMeta('property', 'og:type', 'website')
            ->addMeta('property', 'og:image', 'https://coopcycle.org//images/homepage-banner.jpg')
            ->addMeta('property', 'og:url', $request->getUri())
            ;

        // @see http://ogp.me/#optional
        $this->seoPage
            ->addMeta('property', 'og:locale', $locale)
            ->addMeta('property', 'og:site_name', 'CoopCycle')
            ;
    }
}
