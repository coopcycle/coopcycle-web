<?php

namespace AppBundle\EventListener;

use AppBundle\Service\SettingsManager;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\Translation\TranslatorInterface;

class SeoListener
{
    private $translator;
    private $settingsManager;
    private $seoPage;

    public function __construct(
        TranslatorInterface $translator,
        SettingsManager $settingsManager,
        SeoPageInterface $seoPage
    ) {
        $this->translator = $translator;
        $this->settingsManager = $settingsManager;
        $this->seoPage = $seoPage;
    }

    /**
     * Sets defaults that can be overriden by controllers.
     */
    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        // Skip if this is an API request
        if ($request->attributes->has('_api_resource_class')) {
            return;
        }

        $locale = $request->getLocale();

        $this->seoPage
            ->setTitle($this->settingsManager->get('brand_name') ?: 'CoopCycle');

        $this->seoPage
            ->addMeta('name', 'description', $this->translator->trans('meta.title'));

        $this->seoPage
            ->setLinkCanonical($request->getUri());

        // @see http://ogp.me/#metadata
        $this->seoPage
            ->addMeta('property', 'og:title', $this->seoPage->getTitle())
            ->addMeta('property', 'og:type', 'website')
            ->addMeta('property', 'og:image', 'https://coopcycle.org/images/homepage-banner.jpg')
            ->addMeta('property', 'og:url', $request->getUri());

        // @see http://ogp.me/#optional
        $this->seoPage
            ->addMeta('property', 'og:locale', $locale)
            ->addMeta('property', 'og:site_name', 'CoopCycle');
    }
}
