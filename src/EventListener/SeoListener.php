<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Service\FilterService;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class SeoListener
{
    private static $excluded = [
        'search_geocode',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SettingsManager $settingsManager,
        private readonly SeoPageInterface $seoPage,
        private readonly EntityManagerInterface $entityManager,
        private readonly FilterService $imagineFilter,
        private readonly UploaderHelper $uploaderHelper,
        private readonly Packages $packages,
        private readonly UrlHelper $urlHelper
    )
    {
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
            ->addMeta('property', 'og:image', $this->urlHelper->getAbsoluteUrl($this->packages->getUrl('img/homepage-banner.jpg')))
            ->addMeta('property', 'og:url', $request->getUri());

        // @see http://ogp.me/#optional
        $this->seoPage
            ->addMeta('property', 'og:locale', $locale)
            ->addMeta('property', 'og:site_name', 'CoopCycle');

        $route = $request->attributes->get('_route');
        switch ($route) {
            case 'restaurant':
                $this->seoPageForRestaurant($request);
                break;
            case 'admin_dashboard':
            case 'admin_dashboard_fullscreen':
                $this->addTitlePrefix('adminDashboard.title');
                break;
            case 'admin_orders':
                $this->addTitlePrefix('adminDashboard.orders.title');
                break;
            case 'admin_deliveries':
                $this->addTitlePrefix('adminDashboard.deliveries.title');
                break;
            case 'admin_restaurants':
                $this->addTitlePrefix('adminDashboard.shops.title');
                break;
            case 'admin_stores':
                $this->addTitlePrefix('adminDashboard.stores.title');
                break;
            case 'admin_store':
            case 'admin_store_addresses':
            case 'admin_store_users':
            case 'admin_store_deliveries':
            case 'admin_store_orders_saved':
            case 'admin_store_recurrence_rules':
                $this->seoPageForStore($request);
                break;
            case 'admin_store_recurrence_rule':
                $this->seoPageForRecurrenceRule($request);
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
            try {
                $this->seoPage->addMeta('property', 'og:image',
                    $this->imagineFilter->getUrlOfFilteredImage($imagePath, 'restaurant_thumbnail'));
            } catch (NotLoadableException $e) {}
        }
    }

    private function addTitlePrefix(string $id)
    {
        $this->seoPage
            ->addTitlePrefix($this->translator->trans($id));
    }

    private function seoPageForStore(Request $request)
    {
        $id = $request->attributes->get('id');

        $store = $this->entityManager->getRepository(Store::class)->find($id);

        if (!$store) {
            return;
        }

        $name = $store->getName();
        $this->seoPage->addTitlePrefix($name);
    }

    private function seoPageForRecurrenceRule(Request $request)
    {
        $id = $request->attributes->get('recurrenceRuleId');

        if (!$id) {
            return;
        }

        $this->seoPage->addTitlePrefix($this->translator->trans('subscription.title', ['%id%' => $id]));
    }
}
