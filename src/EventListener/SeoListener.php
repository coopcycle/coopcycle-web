<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Service\FilterService;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class SeoListener
{
    private $translator;
    private $settingsManager;
    private $seoPage;
    private $entityManager;

    /**
     * @var FilterService
     */
    private FilterService $imagineFilter;

    /**
     * @var UploaderHelper
     */
    private UploaderHelper $uploaderHelper;

    private static $excluded = [
        'search_geocode',
    ];

    public function __construct(
        TranslatorInterface $translator,
        SettingsManager $settingsManager,
        SeoPageInterface $seoPage,
        EntityManagerInterface $entityManager,
        FilterService $imagineFilter,
        UploaderHelper $uploaderHelper)
    {
        $this->translator = $translator;
        $this->settingsManager = $settingsManager;
        $this->seoPage = $seoPage;
        $this->entityManager = $entityManager;
        $this->imagineFilter = $imagineFilter;
        $this->uploaderHelper = $uploaderHelper;
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

        // Skip if this is an AJAX request
        if ($request->isXmlHttpRequest()) {
            return;
        }

        // Skip if this is explicitly excluded
        if ($request->attributes->has('_route') && in_array($request->attributes->get('_route'), self::$excluded)) {
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

        switch ($request->attributes->get('_route')) {
            case 'restaurant':
                $this->seoPageForRestaurant($request);
                break;
        }
    }

    private function seoPageForRestaurant(Request $request)
    {
        $id = $request->attributes->get('id');

        $restaurant = $this->entityManager->getRepository(LocalBusiness::class)->find($id);

        if (!$restaurant) {
            return;
        }

        $this->seoPage->addTitleSuffix($restaurant->getName());

        $description = $restaurant->getDescription();
        if (!empty($description)) {
            $this->seoPage->addMeta('name', 'description', $restaurant->getDescription());
        }

        $this->seoPage
            ->addMeta('property', 'og:title', $this->seoPage->getTitle())
            ->addMeta('property', 'og:description', sprintf('%s, %s %s',
                $restaurant->getAddress()->getStreetAddress(),
                $restaurant->getAddress()->getPostalCode(),
                $restaurant->getAddress()->getAddressLocality()
            ))
            // https://developers.facebook.com/docs/reference/opengraph/object-type/restaurant.restaurant/
            ->addMeta('property', 'og:type', 'restaurant.restaurant')
            ->addMeta('property', 'restaurant:contact_info:street_address', $restaurant->getAddress()->getStreetAddress())
            ->addMeta('property', 'place:location:latitude', (string) $restaurant->getAddress()->getGeo()->getLatitude())
            ->addMeta('property', 'place:location:longitude', (string) $restaurant->getAddress()->getGeo()->getLongitude())
            ;

        $website = $restaurant->getWebsite();
        if (!empty($website)) {
            $this->seoPage->addMeta('property', 'restaurant:contact_info:website', $website);
        }

        $locality = $restaurant->getAddress()->getAddressLocality();
        if (!empty($locality)) {
            $this->seoPage->addMeta('property', 'restaurant:contact_info:locality', $locality);
        }

        $imagePath = $this->uploaderHelper->asset($restaurant, 'imageFile');
        if (null !== $imagePath) {
            $this->seoPage->addMeta('property', 'og:image',
                $this->imagineFilter->getUrlOfFilteredImage($imagePath, 'restaurant_thumbnail'));
        }
    }
}
