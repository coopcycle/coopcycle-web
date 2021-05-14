<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardController extends AbstractController
{
    use AccessControlTrait;
    use DeliveryTrait;
    use OrderTrait;
    use RestaurantTrait;
    use StoreTrait;

    protected function getRestaurantRoutes()
    {
        return [
            'restaurants' => 'dashboard_restaurants',
            'restaurant' => 'dashboard_restaurant',
            'menu_taxons' => 'dashboard_restaurant_menu_taxons',
            'menu_taxon' => 'dashboard_restaurant_menu_taxon',
            'products' => 'dashboard_restaurant_products',
            'product_options' => 'dashboard_restaurant_product_options',
            'product_new' => 'dashboard_restaurant_product_new',
            'dashboard' => 'dashboard_restaurant_dashboard',
            'planning' => 'dashboard_restaurant_planning',
            'stripe_oauth_redirect' => 'dashboard_restaurant_stripe_oauth_redirect',
            'preparation_time' => 'dashboard_restaurant_preparation_time',
            'stats' => 'dashboard_restaurant_stats',
            'deposit_refund' => 'dashboard_restaurant_deposit_refund',
            'promotions' => 'dashboard_restaurant_promotions',
            'promotion_new' => 'dashboard_restaurant_new_promotion',
            'promotion' => 'dashboard_restaurant_promotion',
            'product_option_preview' => 'dashboard_restaurant_product_option_preview',
            'reusable_packaging_new' => 'dashboard_restaurant_new_reusable_packaging',
        ];
    }

    protected function getDeliveryRoutes()
    {
        return [
            'list'      => 'profile_tasks',
            'pick'      => 'profile_delivery_pick',
            'deliver'   => 'profile_delivery_deliver',
            'view'      => 'dashboard_delivery',
            'store_new' => 'dashboard_store_delivery_new',
            'store_addresses' => 'dashboard_store_addresses',
            'download_images' => 'dashboard_store_delivery_download_images',
        ];
    }

    protected function getStoreList()
    {
        return [ $this->getUser()->getStores(), 1, 1 ];
    }

    protected function getOrderList(Request $request, $showCanceled = false)
    {
        return [];
    }

    public function indexAction(Request $request,
        SlugifyInterface $slugify,
        TranslatorInterface $translator,
        PaginatorInterface $paginator,
        EntityManagerInterface $entityManager,
        TaxesHelper $taxesHelper)
    {
        $user = $this->getUser();

        if ($user->hasRole('ROLE_STORE') && $request->attributes->has('_store')) {

            $store = $request->attributes->get('_store');

            $routes = $request->attributes->has('routes') ? $request->attributes->get('routes') : [];
            $routes['import_success'] = 'dashboard';
            $routes['stores'] = 'dashboard';
            $routes['store'] = 'dashboard_store';

            $request->attributes->set('layout', 'dashboard.html.twig');
            $request->attributes->set('routes', $routes);

            return $this->storeDeliveriesAction($store->getId(), $request, $translator, $paginator);
        }

        if ($user->hasRole('ROLE_RESTAURANT') && $request->attributes->has('_restaurant')) {

            $restaurant = $request->attributes->get('_restaurant');

            return $this->statsAction($restaurant->getId(), $request, $slugify, $translator, $entityManager, $paginator, $taxesHelper);
        }

        return $this->redirectToRoute('nucleos_profile_profile_show');
    }
}
