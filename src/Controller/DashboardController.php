<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\InjectAuthTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\CubeJs\TokenFactory as CubeJsTokenFactory;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Service\DeliveryImportStatusManager;
use AppBundle\Spreadsheet\Column\ColumnDetector;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Knp\Component\Pager\PaginatorInterface;
use League\Flysystem\Filesystem;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardController extends AbstractController
{
    use AccessControlTrait;
    use DeliveryTrait;
    use OrderTrait;
    use RestaurantTrait;
    use StoreTrait;
    use InjectAuthTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly JWTTokenManagerInterface $JWTTokenManager,
        protected NormalizerInterface $normalizer,
        protected SerializerInterface $serializer,
        protected bool $rdcEnabled = false,
    )
    {
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
            'promotion_archive' => 'dashboard_restaurant_archive_promotion',
            'promotion_feature' => 'dashboard_restaurant_feature_promotion',
            'promotion_coupon' => 'dashboard_restaurant_promotion_coupon',
            'product_option_preview' => 'dashboard_restaurant_product_option_preview',
            'reusable_packaging_new' => 'dashboard_restaurant_new_reusable_packaging',
            'mercadopago_oauth_redirect' => 'dashboard_restaurant_mercadopago_oauth_redirect',
            'mercadopago_oauth_remove' => 'dashboard_restaurant_mercadopago_oauth_remove',
            'image_from_pixabay' => 'dashboard_restaurant_image_from_pixabay',
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
        Filesystem $deliveryImportsFilesystem,
        LoggerInterface $logger,
    )
    {
        $user = $this->getUser();

        if ($user->hasRole('ROLE_STORE') && $request->attributes->has('_store')) {

            $store = $request->attributes->get('_store');

            $routes = $request->attributes->has('routes') ? $request->attributes->get('routes') : [];
            $routes['import_success'] = 'dashboard';
            $routes['import_mapping'] = 'dashboard_delivery_import_mapping';
            $routes['stores'] = 'dashboard';
            $routes['store'] = 'dashboard_store';

            $request->attributes->set('layout', 'dashboard.html.twig');
            $request->attributes->set('routes', $routes);

            return $this->storeDeliveriesAction(
                $store->getId(),
                $request,
                $paginator,
                deliveryRepository: $deliveryRepository,
                messageBus: $messageBus,
                entityManager: $entityManager,
                hashids8: $hashids8,
                deliveryImportsFilesystem: $deliveryImportsFilesystem,
                slugify: $slugify,
                logger: $logger,
            );
        }

        if ($user->hasRole('ROLE_RESTAURANT') && $request->attributes->has('_restaurant')) {

            $restaurant = $request->attributes->get('_restaurant');

            return $this->statsAction($restaurant->getId(), $request, $slugify, $translator, $entityManager, $paginator, $taxesHelper, $tokenFactory);
        }

        return $this->redirectToRoute('profile_edit');
    }

    /**
     * A store owner saying which field each column of the file they uploaded holds
     */
    public function deliveryImportMappingAction(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        Filesystem $deliveryImportsFilesystem,
        DeliverySpreadsheetParser $spreadsheetParser,
        ColumnDetector $columnDetector,
        DeliveryImportStatusManager $statusManager,
        MessageBusInterface $messageBus,
        LoggerInterface $logger,
    )
    {
        return $this->handleDeliveryImportMapping(
            id: $id,
            request: $request,
            entityManager: $entityManager,
            filesystem: $deliveryImportsFilesystem,
            parser: $spreadsheetParser,
            detector: $columnDetector,
            statusManager: $statusManager,
            messageBus: $messageBus,
            logger: $logger,
            routeTo: 'dashboard',
        );
    }
}
