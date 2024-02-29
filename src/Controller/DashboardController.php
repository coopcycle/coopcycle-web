<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\CubeJs\TokenFactory as CubeJsTokenFactory;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Knp\Component\Pager\PaginatorInterface;
use League\Flysystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardController extends AbstractController
{
    use AccessControlTrait;
    use DeliveryTrait;
    use OrderTrait;
    use RestaurantTrait;
    use StoreTrait;

    public function __construct(
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        bool $adhocOrderEnabled
    )
    {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->adhocOrderEnabled = $adhocOrderEnabled;
    }

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
            'mercadopago_oauth_redirect' => 'dashboard_restaurant_mercadopago_oauth_redirect',
            'mercadopago_oauth_remove' => 'dashboard_restaurant_mercadopago_oauth_remove',
            'image_from_url' => 'dashboard_restaurant_image_from_url',
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

    protected function getOrderList(Request $request, PaginatorInterface $paginator, $showCanceled = false)
    {
        return [];
    }

    public function indexAction(Request $request,
        SlugifyInterface $slugify,
        TranslatorInterface $translator,
        PaginatorInterface $paginator,
        EntityManagerInterface $entityManager,
        TaxesHelper $taxesHelper,
        CubeJsTokenFactory $tokenFactory,
        DeliveryRepository $deliveryRepository,
        MessageBusInterface $messageBus,
        Hashids $hashids8,
        Filesystem $deliveryImportsFilesystem
    )
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

            return $this->storeDeliveriesAction(
                $store->getId(),
                $request,
                $paginator,
                deliveryRepository: $deliveryRepository,
                entityManager: $entityManager,
                hashids8: $hashids8,
                deliveryImportsFilesystem: $deliveryImportsFilesystem,
                messageBus: $messageBus
            );
        }

        if ($user->hasRole('ROLE_RESTAURANT') && $request->attributes->has('_restaurant')) {

            $restaurant = $request->attributes->get('_restaurant');

            return $this->statsAction($restaurant->getId(), $request, $slugify, $translator, $entityManager, $paginator, $taxesHelper, $tokenFactory);
        }

        return $this->redirectToRoute('profile_edit');
    }
}
