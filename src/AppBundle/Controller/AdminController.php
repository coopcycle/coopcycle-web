<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\AdminDashboardTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\LocalBusinessTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Form\RegistrationType;
use AppBundle\Form\RestaurantAdminType;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant\Pledge;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Store;
use AppBundle\Entity\TimeSlot;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Task;
use AppBundle\Entity\Zone;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Form\ApiAppType;
use AppBundle\Form\BannerType;
use AppBundle\Form\EmbedSettingsType;
use AppBundle\Form\OrderType;
use AppBundle\Form\PricingRuleSetType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\GeoJSONUploadType;
use AppBundle\Form\MaintenanceType;
use AppBundle\Form\PackageSetType;
use AppBundle\Form\SettingsType;
use AppBundle\Form\StripeLivemodeType;
use AppBundle\Form\Sylius\Promotion\CreditNoteType;
use AppBundle\Form\TimeSlotType;
use AppBundle\Form\TaxationType;
use AppBundle\Form\ZoneCollectionType;
use AppBundle\Service\ActivityManager;
use AppBundle\Service\EmailManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\TaskManager;
use AppBundle\Sylius\Order\OrderTransitions;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Checker\Rule\IsCustomerRuleChecker;
use AppBundle\Utils\MessageLoggingTwigSwiftMailer;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use FOS\UserBundle\Model\UserInterface;
use Predis\Client as Redis;
use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionCouponType;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Promotion\Model\Promotion;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Taxation\Model\TaxCategory;
use Sylius\Component\Taxation\Model\TaxRate;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Service\TagManager;

class AdminController extends Controller
{
    const ITEMS_PER_PAGE = 20;

    use AccessControlTrait;
    use AdminDashboardTrait;
    use DeliveryTrait;
    use OrderTrait {
        orderListAction as baseOrderListAction;
    }
    use LocalBusinessTrait;
    use RestaurantTrait;
    use StoreTrait;
    use UserTrait;

    protected function getRestaurantRoutes()
    {
        return [
            'restaurants' => 'admin_restaurants',
            'restaurant' => 'admin_restaurant',
            'menu_taxons' => 'admin_restaurant_menu_taxons',
            'menu_taxon' => 'admin_restaurant_menu_taxon',
            'products' => 'admin_restaurant_products',
            'product_options' => 'admin_restaurant_product_options',
            'product_new' => 'admin_restaurant_product_new',
            'dashboard' => 'admin_restaurant_dashboard',
            'planning' => 'admin_restaurant_planning',
            'stripe_oauth_redirect' => 'admin_restaurant_stripe_oauth_redirect',
            'preparation_time' => 'admin_restaurant_preparation_time',
            'stats' => 'admin_restaurant_stats'
        ];
    }

    /**
     * @Route("/admin", name="admin_index")
     * @Template("@App/admin/dashboard.html.twig")
     */
    public function indexAction(Request $request)
    {
        return $this->dashboardAction($request);
    }

    protected function getOrderList(Request $request)
    {
        $showCanceled = false;
        if ($request->query->has('show_canceled')) {
            $showCanceled = $request->query->getBoolean('show_canceled');
        } elseif ($request->cookies->has('__show_canceled')) {
            $showCanceled = $request->cookies->getBoolean('__show_canceled');
        }

        $qb = $this->get('sylius.repository.order')
            ->createQueryBuilder('o');
        $qb
            ->andWhere('o.state != :state')
            ->setParameter('state', OrderInterface::STATE_CART);

        if (!$showCanceled) {
            $qb
                ->andWhere('o.state != :state_cancelled')
                ->setParameter('state_cancelled', OrderInterface::STATE_CANCELLED);
        }

        $count = (clone $qb)
            ->select('COUNT(o)')
            ->getQuery()
            ->getSingleScalarResult();

        $pages  = ceil($count / self::ITEMS_PER_PAGE);
        $page   = $request->query->get('p', 1);
        $offset = self::ITEMS_PER_PAGE * ($page - 1);

        $orders = (clone $qb)
            ->setMaxResults(self::ITEMS_PER_PAGE)
            ->setFirstResult($offset)
            ->orderBy('o.shippedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return [ $orders, $pages, $page ];
    }

    /**
     * @Route("/admin/orders/{id}", name="admin_order")
     * @Template
     */
    public function orderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->container->get('sylius.repository.order')->find($id);

        if (!$order) {
            throw $this->createNotFoundException(sprintf('Order #%d does not exist', $id));
        }

        $form = $this->createForm(OrderType::class, $order);

        $form->handleRequest($request);

        foreach ($form->get('payments') as $paymentForm) {
            if ($paymentForm->isSubmitted() && $paymentForm->isValid()) {
                if ($form->getClickedButton() && 'refund' === $form->getClickedButton()->getName()) {

                    $payment = $paymentForm->getData();
                    $amount = $paymentForm->get('amount')->getData();
                    $refundApplicationFee = $paymentForm->get('refundApplicationFee')->getData();

                    $orderManager->refundPayment($payment, $amount, $refundApplicationFee);

                    $this->get('sylius.manager.order')->flush();

                    return $this->redirectToRoute('admin_orders');
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->getClickedButton()) {

                if ('accept' === $form->getClickedButton()->getName()) {
                    $orderManager->accept($order);
                }

                if ('fulfill' === $form->getClickedButton()->getName()) {
                    if ($order->isFoodtech()) {
                        throw new BadRequestHttpException(sprintf('Order #%d should not be fulfilled directly', $order->getId()));
                    }

                    $orderManager->fulfill($order);
                }

                if ('cancel' === $form->getClickedButton()->getName()) {
                    $orderManager->cancel($order);
                }

                $this->get('sylius.manager.order')->flush();

                return $this->redirectToRoute('admin_orders');
            }
        }

        $pickupAddress = null;
        $dropoffAddress = null;
        $pickupAt = null;
        $dropoffAt = null;

        if ($order->isFoodtech()) {
            $pickupAddress = $order->getRestaurant()->getAddress();
            $dropoffAddress = $order->getShippingAddress();
            $pickupAt = $order->getTimeline()->getPickupExpectedAt();
            $dropoffAt = $order->getTimeline()->getDropoffExpectedAt();
        } elseif (null !== $order->getDelivery()) {
            $pickupAddress = $order->getDelivery()->getPickup()->getAddress();
            $dropoffAddress = $order->getDelivery()->getDropoff()->getAddress();
            $pickupAt = $order->getDelivery()->getPickup()->getDoneBefore();
            $dropoffAt = $order->getDelivery()->getDropoff()->getDoneBefore();
        }

        return $this->render('@App/order/service.html.twig', [
            'layout' => '@App/admin.html.twig',
            'order' => $order,
            'pickup_address' => $pickupAddress,
            'dropoff_address' => $dropoffAddress,
            'pickup_at' => $pickupAt,
            'dropoff_at' => $dropoffAt,
            'form' => $form->createView(),
        ]);
    }

    public function orderListAction(Request $request)
    {
        return $this->baseOrderListAction($request);
    }

    public function foodtechDashboardAction($date, Request $request, Redis $redis)
    {
        $date = new \DateTime($date);

        $orders = $this->get('sylius.repository.order')->findByShippedAt($date);

        $ordersNormalized = $this->get('serializer')->normalize($orders, 'jsonld', [
            'resource_class' => Order::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['order', 'address', 'place']
        ]);

        $preparationDelay = $redis->get('foodtech:preparation_delay');
        if (!$preparationDelay) {
            $preparationDelay = 0;
        }

        return $this->render('@App/admin/foodtech_dashboard.html.twig', [
            'orders' => $orders,
            'date' => $date,
            'orders_normalized' => $ordersNormalized,
            'routes' => $request->attributes->get('routes'),
            'jwt' => $request->getSession()->get('_jwt'),
            'preparation_delay' => intval($preparationDelay),
        ]);
    }

    public function foodtechSettingsAction(Request $request, Redis $redis)
    {
        $preparationDelay = $request->request->get('preparation_delay');
        if (0 === $preparationDelay) {
            $redis->del('foodtech:preparation_delay');
        } else {
            $redis->set('foodtech:preparation_delay', $preparationDelay);
        }

        return new JsonResponse([
            'preparation_delay' => $preparationDelay,
        ]);
    }

    /**
     * @Route("/admin/users", name="admin_users")
     * @Template
     */
    public function usersAction(Request $request)
    {
        $users = $this->getDoctrine()
            ->getRepository('AppBundle:ApiUser')
            ->findBy([], ['id' => 'DESC']);

        return array(
            'users' => $users,
        );
    }

    /**
     * @Route("/admin/users/add", name="admin_users_add")
     * @Template
     */
    public function userAddAction(Request $request)
    {
        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $user->setEnabled(true);

            $this->getDoctrine()->getManagerForClass(ApiUser::class)->persist($user);
            $this->getDoctrine()->getManagerForClass(ApiUser::class)->flush();
            return $this->redirectToRoute('admin_users');
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/admin/user/{username}", name="admin_user_details")
     * @Template
     */
    public function userAction($username, Request $request)
    {
        // @link https://symfony.com/doc/current/bundles/FOSUserBundle/user_manager.html
        $userManager = $this->get('fos_user.user_manager');

        $user = $userManager->findUserByUsername($username);

        return [
            'user' => $user,
        ];
    }

    /**
     * @Route("/admin/user/{username}/edit", name="admin_user_edit")
     * @Template
     */
    public function userEditAction($username, Request $request)
    {
        // @link https://symfony.com/doc/current/bundles/FOSUserBundle/user_manager.html
        $userManager = $this->get('fos_user.user_manager');

        $user = $userManager->findUserByUsername($username);

        // Roles that can be edited by admin
        $editableRoles = ['ROLE_ADMIN', 'ROLE_COURIER', 'ROLE_RESTAURANT', 'ROLE_STORE'];

        $originalRoles = array_filter($user->getRoles(), function($role) use ($editableRoles) {
            return in_array($role, $editableRoles);
        });

        $editForm = $this->createForm(UpdateProfileType::class, $user, [
            'with_restaurants' => true,
            'with_stores' => true,
            'with_roles' => true,
            'editable_roles' => $editableRoles
        ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {

            $userManager = $this->getDoctrine()->getManagerForClass(ApiUser::class);

            $user = $editForm->getData();

            $roles = $editForm->get('roles')->getData();

            $rolesToRemove = array_diff($originalRoles, $roles);

            foreach ($rolesToRemove as $role) {
                $user->removeRole($role);
            }

            foreach ($roles as $role) {
                if (!$user->hasRole($role)) {
                    $user->addRole($role);
                }
            }

            $userManager->persist($user);
            $userManager->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirectToRoute('admin_user_edit', ['username' => $user->getUsername()]);
        }

        return [
            'form' => $editForm->createView(),
            'user' => $user,
        ];
    }

    /**
     * @Route("/admin/user/{username}/tracking", name="admin_user_tracking")
     * @Template("@App/user/tracking.html.twig")
     */
    public function userTrackingAction($username, Request $request)
    {
        $userManager = $this->get('fos_user.user_manager');
        $user = $userManager->findUserByUsername($username);

        return $this->userTracking($user, 'admin');
    }

    protected function getRestaurantList(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Restaurant::class);

        $countAll = $repository
            ->createQueryBuilder('r')->select('COUNT(r)')
            ->getQuery()->getSingleScalarResult();

        $pages = ceil($countAll / self::ITEMS_PER_PAGE);
        $page = $request->query->get('p', 1);

        $offset = self::ITEMS_PER_PAGE * ($page - 1);

        $restaurants = $repository->findBy([], [
            'id' => 'DESC',
        ], self::ITEMS_PER_PAGE, $offset);

        return [ $restaurants, $pages, $page ];
    }

    /**
     * @Route("/admin/deliveries", name="admin_deliveries")
     * @Template()
     */
    public function deliveriesAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Delivery::class);

        $qb = $repository->createQueryBuilder('d')->orderBy('d.createdAt', 'DESC');

        $filters = [];

        if ($request->query->has('store')) {
            $store = $this->getDoctrine()
                ->getRepository(Store::class)
                ->find($request->query->get('store'));

            if ($store) {
                $qb
                    ->andWhere('d.store = :store')
                    ->setParameter('store', $store);
                $filters[] = [
                    'name' => 'store',
                    'value' => $store->getId(),
                    'label' => $store->getName()
                ];
            }
        }

        $deliveries = $this->get('knp_paginator')->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        return [
            'deliveries' => $deliveries,
            'filters' => $filters,
            'routes' => $this->getDeliveryRoutes(),
            'stores' => $this->getDoctrine()->getRepository(Store::class)->findBy([], ['name' => 'ASC'])
        ];
    }

    protected function getDeliveryRoutes()
    {
        return [
            'list'      => 'admin_deliveries',
            'pick'      => 'admin_delivery_pick',
            'deliver'   => 'admin_delivery_deliver',
            'view'      => 'admin_delivery',
            'store_new' => 'admin_store_delivery_new'
        ];
    }

    /**
     * @Route("/admin/settings/taxation", name="admin_taxation_settings")
     * @Template
     */
    public function taxationSettingsAction(Request $request)
    {
        $taxCategoryRepository = $this->get('sylius.repository.tax_category');

        $taxCategories = $taxCategoryRepository->findAll();

        return [
            'taxCategories' => $taxCategories
        ];
    }

    /**
     * @Route("/admin/settings/taxation/new", name="admin_taxation_settings_new")
     * @Template("@App/admin/taxation_form.html.twig")
     */
    public function newTaxationAction(Request $request, SlugifyInterface $slugify)
    {
        $taxRate = new TaxRate();
        $taxRate->setIncludedInPrice(true);
        $taxRate->setCalculator('default');

        $taxCategory = new TaxCategory();
        $taxCategory->addRate($taxRate);

        $form = $this->createForm(TaxationType::class, $taxCategory);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $taxCategory = $form->getData();
            $taxRate = $form->get('taxRate')->getData();

            $taxCategoryCode = $slugify->slugify(
                $taxCategory->getName(),
                ['separator' => '_']
            );

            $taxCategory->setCode($taxCategoryCode);

            $taxRateCode = $slugify->slugify(
                join('_', [sprintf('vat_%02d', $taxRate->getAmount() * 100), $taxCategoryCode]),
                ['separator' => '_']
            );

            $taxRate->setCode($taxRateCode);
            $taxRate->setName(sprintf('VAT %s%%', $taxRate->getAmount() * 100));

            $taxCategoryRepository = $this->get('sylius.repository.tax_category');
            $taxCategoryRepository->add($taxCategory);

            return $this->redirectToRoute('admin_taxation_settings');
        }
        return [
            'form' => $form->createView()
        ];
    }

    /**
     * @Route("/admin/settings/taxation/{code}", name="admin_taxation_settings_edit")
     * @Template("@App/admin/taxation_form.html.twig")
     */
    public function editTaxationAction($code, Request $request)
    {
        $taxCategory = $this->get('sylius.repository.tax_category')->findOneByCode($code);

        $form = $this->createForm(TaxationType::class, $taxCategory);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $taxCategory = $form->getData();

            $this->get('sylius.manager.tax_category')->flush();

            return $this->redirectToRoute('admin_taxation_settings');
        }

        return [
            'form' => $form->createView()
        ];
    }

    /**
     * @Route("/admin/settings/tags", name="admin_tags")
     */
    public function tagsAction(Request $request, TagManager $tagManager)
    {
        if ($request->isMethod('POST') && $request->request->has('delete')){
            $id = $request->request->get('tag');
            $tag = $this->getDoctrine()->getRepository(Tag::class)->find($id);
            $tagManager->untagAll($tag);
            $this->getDoctrine()->getManagerForClass(Tag::class)->remove($tag);
            $this->getDoctrine()->getManagerForClass(Tag::class)->flush();

            return  $this->redirectToRoute('admin_tags');
        }

        $tags = $this->getDoctrine()->getRepository(Tag::class)->findAll();

        if ($request->query->has('format')) {
            if ('json' === $request->query->get('format')) {
                $data = array_map(function (Tag $tag) {
                    return [
                        'id' => $tag->getId(),
                        'name' => $tag->getName(),
                        'slug' => $tag->getSlug(),
                        'color' => $tag->getColor(),
                    ];
                }, $tags);

                return new JsonResponse($data);
            }
        }

        return $this->render('@App/admin/tags.html.twig', [
            'tags' => $tags
        ]);
    }

    /**
     * @Route("/admin/deliveries/pricing", name="admin_deliveries_pricing")
     * @Template("@App/admin/pricing.html.twig")
     */
    public function pricingRuleSetsAction(Request $request)
    {
        $ruleSets = $this->getDoctrine()
            ->getRepository(Delivery\PricingRuleSet::class)
            ->findAll();

        return [
            'ruleSets' => $ruleSets
        ];
    }

    private function renderPricingRuleSetForm(Delivery\PricingRuleSet $ruleSet, Request $request)
    {
        $originalRules = new ArrayCollection();

        foreach ($ruleSet->getRules() as $rule) {
            $originalRules->add($rule);
        }

        $zoneRepo = $this->getDoctrine()->getRepository(Zone::class);
        $zones = $zoneRepo->findAll();
        $zoneNames = [];
        foreach ($zones as $zone) {
            array_push($zoneNames, $zone->getName());
        }

        $form = $this->createForm(PricingRuleSetType::class, $ruleSet);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $ruleSet = $form->getData();

            $em = $this->getDoctrine()->getManagerForClass(Delivery\PricingRule::class);

            foreach ($originalRules as $originalRule) {
                if (!$ruleSet->getRules()->contains($originalRule)) {
                    $em->remove($originalRule);
                }
            }

            foreach ($ruleSet->getRules() as $rule) {
                $rule->setRuleSet($ruleSet);
            }

            if (null === $ruleSet->getId()) {
                $em->persist($ruleSet);
            }

            $em->flush();

            return $this->redirectToRoute('admin_deliveries_pricing');
        }

        return [
            'form' => $form->createView(),
            'zoneNames' => json_encode($zoneNames)
        ];
    }

    /**
     * @Route("/admin/deliveries/pricing/new", name="admin_deliveries_pricing_ruleset_new")
     * @Template("@App/admin/pricing_rule_set.html.twig")
     */
    public function newPricingRuleSetAction(Request $request)
    {
        $ruleSet = new Delivery\PricingRuleSet();

        return $this->renderPricingRuleSetForm($ruleSet, $request);
    }

    /**
     * @Route("/admin/deliveries/pricing/{id}", name="admin_deliveries_pricing_ruleset")
     * @Template("@App/admin/pricing_rule_set.html.twig")
     */
    public function pricingRuleSetAction($id, Request $request)
    {
        $ruleSet = $this->getDoctrine()
            ->getRepository(Delivery\PricingRuleSet::class)
            ->find($id);

        return $this->renderPricingRuleSetForm($ruleSet, $request);
    }

    /**
     * @Route("/admin/zones/{id}/delete", methods={"POST"}, name="admin_zone_delete")
     * @Template
     */
    public function deleteZoneAction($id, Request $request)
    {
        $zone = $this->getDoctrine()->getRepository(Zone::class)->find($id);

        $this->getDoctrine()->getManagerForClass(Zone::class)->remove($zone);
        $this->getDoctrine()->getManagerForClass(Zone::class)->flush();

        return $this->redirectToRoute('admin_zones');
    }

    /**
     * @Route("/admin/zones", name="admin_zones")
     * @Template
     */
    public function zonesAction(Request $request)
    {
        $zoneCollection = new \stdClass();
        $zoneCollection->zones = [];

        $uploadForm = $this->createForm(GeoJSONUploadType::class);
        $zoneCollectionForm = $this->createForm(ZoneCollectionType::class, $zoneCollection);

        $zoneCollectionForm->handleRequest($request);
        if ($zoneCollectionForm->isSubmitted() && $zoneCollectionForm->isValid()) {

            $zoneCollection = $zoneCollectionForm->getData();

            foreach ($zoneCollection->zones as $zone) {
                $this->getDoctrine()->getManagerForClass(Zone::class)->persist($zone);
            }

            $this->getDoctrine()->getManagerForClass(Zone::class)->flush();

            return $this->redirectToRoute('admin_zones');
        }

        $uploadForm->handleRequest($request);
        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $geojson = $uploadForm->getData();
            foreach ($geojson as $feature) {
                $zone = new Zone();
                $zone->setGeoJSON($feature->getGeometry()->jsonSerialize());
                $zoneCollection->zones[] = $zone;
            }

            $zoneCollectionForm->setData($zoneCollection);
        }

        $zones = $this->getDoctrine()->getRepository(Zone::class)->findAll();

        return [
            'zones' => $zones,
            'upload_form' => $uploadForm->createView(),
            'zone_collection_form' => $zoneCollectionForm->createView(),
        ];
    }

    public function getStoreList(Request $request)
    {
        $stores = $this->getDoctrine()->getRepository(Store::class)->findAll();

        return [ $stores, 1, 1 ];
    }

    public function newStoreAction(Request $request)
    {
        $store = new Store();

        return $this->renderStoreForm($store, $request);
    }

    /**
     * @Route("/admin/restaurants/search", name="admin_restaurants_search")
     * @Template()
     */
    public function searchRestaurantsAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Restaurant::class);

        $results = $repository->search($request->query->get('q'));

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {

            $data = array_map(function (Restaurant $restaurant) {
                return [
                    'id' => $restaurant->getId(),
                    'name' => $restaurant->getName(),
                ];
            }, $results);

            return new JsonResponse($data);
        }
    }

    /**
     * @Route("/admin/stores/search", name="admin_stores_search")
     * @Template()
     */
    public function searchStoresAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Store::class);

        $qb = $repository->createQueryBuilder('s');
        $qb
            ->where('LOWER(s.name) LIKE :q')
            ->setParameter('q', '%' . strtolower($request->query->get('q')) . '%');

        $results = $qb->getQuery()->getResult();

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {

            $data = array_map(function (Store $store) {
                return [
                    'id' => $store->getId(),
                    'name' => $store->getName(),
                    'pricingRuleSetId' => $store->getPricingRuleSet() ? $store->getPricingRuleSet()->getId() : null,
                    'address' => [
                        'addressLocality' => $store->getAddress()->getAddressLocality(),
                        'addressCountry' => $store->getAddress()->getAddressCountry(),
                        'streetAddress' => $store->getAddress()->getStreetAddress(),
                        'postalCode' => $store->getAddress()->getPostalCode(),
                        'latitude' => $store->getAddress()->getGeo()->getLatitude(),
                        'longitude' => $store->getAddress()->getGeo()->getLongitude()
                    ]
                ];
            }, $results);

            return new JsonResponse($data);
        }
    }

    /**
     * @Route("/admin/users/search", name="admin_users_search")
     * @Template()
     */
    public function searchUsersAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(ApiUser::class);

        $results = $repository->search($request->query->get('q'));

        if ($request->query->has('format') && 'json' === $request->query->get('format')) {

            $data = array_map(function (ApiUser $user) {

                $text = sprintf('%s (%s)', $user->getEmail(), $user->getUsername());

                return [
                    'id' => $user->getId(),
                    'name' => $text,
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                    'firstName' => $user->getGivenName(),
                    'lastName' => $user->getFamilyName(),
                ];
            }, $results);

            return new JsonResponse($data);
        }
    }

    /**
     * @Route("/admin/settings", name="admin_settings")
     * @Template()
     */
    public function settingsAction(Request $request, SettingsManager $settingsManager, Redis $redis)
    {
        /* Stripe live mode */

        $isStripeLivemode = $settingsManager->isStripeLivemode();
        $canEnableStripeLivemode = $settingsManager->canEnableStripeLivemode();
        $stripeLivemodeForm = $this->createForm(StripeLivemodeType::class);

        $stripeLivemodeForm->handleRequest($request);
        if ($stripeLivemodeForm->isSubmitted() && $stripeLivemodeForm->isValid()) {

            if ($stripeLivemodeForm->getClickedButton()) {
                if ('enable' === $stripeLivemodeForm->getClickedButton()->getName()) {
                    $settingsManager->set('stripe_livemode', 'yes');
                }
                if ('disable' === $stripeLivemodeForm->getClickedButton()->getName()) {
                    $settingsManager->set('stripe_livemode', 'no');
                }
                if ('disable_and_enable_maintenance' === $stripeLivemodeForm->getClickedButton()->getName()) {
                    $redis->set('maintenance', '1');
                    $settingsManager->set('stripe_livemode', 'no');
                }
                $settingsManager->flush();
            }

            return $this->redirectToRoute('admin_settings');
        }

        /* Maintenance */

        $maintenanceForm = $this->createForm(MaintenanceType::class);

        $maintenanceForm->handleRequest($request);
        if ($maintenanceForm->isSubmitted() && $maintenanceForm->isValid()) {

            if ($maintenanceForm->getClickedButton()) {
                if ('enable' === $maintenanceForm->getClickedButton()->getName()) {
                    $maintenanceMessage = $maintenanceForm->get('message')->getData();

                    $redis->set('maintenance_message', $maintenanceMessage);
                    $redis->set('maintenance', '1');
                }
                if ('disable' === $maintenanceForm->getClickedButton()->getName()) {
                    $redis->del('maintenance_message');
                    $redis->del('maintenance');
                }
            }

            return $this->redirectToRoute('admin_settings');
        }

        /* Banner */

        $bannerForm = $this->createForm(BannerType::class);

        $bannerForm->handleRequest($request);
        if ($bannerForm->isSubmitted() && $bannerForm->isValid()) {

            if ($bannerForm->getClickedButton()) {
                if ('enable' === $bannerForm->getClickedButton()->getName()) {
                    $bannerMessage = $bannerForm->get('message')->getData();

                    $redis->set('banner_message', $bannerMessage);
                    $redis->set('banner', '1');
                }
                if ('disable' === $bannerForm->getClickedButton()->getName()) {
                    $redis->del('banner_message');
                    $redis->del('banner');
                }
            }

            return $this->redirectToRoute('admin_settings');
        }

        /* Settings */

        $settings = $settingsManager->asEntity();
        $form = $this->createForm(SettingsType::class, $settings);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();

            foreach ($data as $name => $value) {
                $settingsManager->set($name, $value);
            }

            $settingsManager->flush();

            return $this->redirectToRoute('admin_settings');
        }

        return [
            'timezone' => ini_get('date.timezone'),
            'form' => $form->createView(),
            'maintenance_form' => $maintenanceForm->createView(),
            'maintenance' => $redis->get('maintenance'),
            'banner_form' => $bannerForm->createView(),
            'banner' => $redis->get('banner'),
            'stripe_livemode' => $isStripeLivemode,
            'stripe_livemode_form' => $stripeLivemodeForm->createView(),
            'can_enable_stripe_livemode' => $canEnableStripeLivemode,
        ];
    }

    /**
     * @Route("/admin/embed", name="admin_embed")
     * @Template()
     */
    public function embedAction(Request $request, SettingsManager $settingsManager)
    {
        $pricingRuleSet = null;

        $pricingRuleSetId = $settingsManager->get('embed.delivery.pricingRuleSet');
        $withVehicle = $settingsManager->getBoolean('embed.delivery.withVehicle');

        if ($pricingRuleSetId) {
            $pricingRuleSet = $this->getDoctrine()
                ->getRepository(PricingRuleSet::class)
                ->find($pricingRuleSetId);
        }

        $form = $this->createForm(EmbedSettingsType::class);
        $form->get('pricingRuleSet')->setData($pricingRuleSet);
        $form->get('withVehicle')->setData($withVehicle);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $pricingRuleSet = $form->get('pricingRuleSet')->getData();
            $withVehicle = $form->get('withVehicle')->getData();

            $settingsManager->set('embed.delivery.pricingRuleSet', $pricingRuleSet ? $pricingRuleSet->getId() : null, 'embed');
            $settingsManager->set('embed.delivery.withVehicle', $withVehicle ? 'yes' : 'no', 'embed');
            $settingsManager->flush();

            return $this->redirect($request->headers->get('referer'));
        }

        return [
            'pricing_rule_set' => $pricingRuleSet,
            'embed_settings_form' => $form->createView(),
        ];
    }

    /**
     * @Route("/admin/activity", name="admin_activity")
     */
    public function activityAction(Request $request, ActivityManager $activityManager)
    {
        $date = new \DateTime();
        if ($request->query->has('date')) {
            $date = new \DateTime($request->query->get('date'));
        }

        $events = $activityManager->getEventsByDate($date);

        return $this->render('@App/admin/activity.html.twig', [
            'events' => $events,
            'date' => $date
        ]);
    }

    /**
     * @Route("/admin/api/apps", name="admin_api_apps")
     */
    public function apiAppsAction(Request $request)
    {
        $apiApps = $this->getDoctrine()
            ->getRepository(ApiApp::class)
            ->findAll();

        return $this->render('@App/admin/api_apps.html.twig', [
            'api_apps' => $apiApps
        ]);
    }

    /**
     * @Route("/admin/api/apps/new", name="admin_new_api_app")
     */
    public function newApiAppAction(Request $request)
    {
        $apiApp = new ApiApp();

        $form = $this->createForm(ApiAppType::class, $apiApp);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $apiApp = $form->getData();

            $this->getDoctrine()
                ->getManagerForClass(ApiApp::class)
                ->persist($apiApp);

            $this->getDoctrine()
                ->getManagerForClass(ApiApp::class)
                ->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('api_apps.created.message')
            );

            return $this->redirectToRoute('admin_api_app', [ 'id' => $apiApp->getId() ]);
        }

        return $this->render('@App/admin/api_app_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/api/apps/{id}", name="admin_api_app")
     */
    public function apiAppAction($id, Request $request)
    {
        $apiApp = $this->getDoctrine()
            ->getRepository(ApiApp::class)
            ->find($id);

        $form = $this->createForm(ApiAppType::class, $apiApp);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $apiApp = $form->getData();

            $this->getDoctrine()
                ->getManagerForClass(ApiApp::class)
                ->flush();

            return $this->redirectToRoute('admin_api_apps');
        }

        return $this->render('@App/admin/api_app_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/promotions", name="admin_promotions")
     */
    public function promotionsAction(Request $request)
    {
        $promotions = $this->get('sylius.repository.promotion')->findAll();

        $promotionCoupons = $this->get('sylius.repository.promotion_coupon')->findAll();

        $freeDeliveryCoupons = [];
        $creditNoteCoupons = [];

        foreach ($promotionCoupons as $promotionCoupon) {
            if ($promotionCoupon->getPromotion()->getCode() === 'FREE_DELIVERY') {
                $freeDeliveryCoupons[] = $promotionCoupon;
            } else {
                $creditNoteCoupons[] = $promotionCoupon;
            }
        }

        $freeDeliveryPromotion = $this->get('sylius.repository.promotion')->findOneByCode('FREE_DELIVERY');

        return $this->render('@App/admin/promotions.html.twig', [
            'promotions' => $promotions,
            'free_delivery_coupons' => $freeDeliveryCoupons,
            'credit_note_coupons' => $creditNoteCoupons,
            'free_delivery_promotion' => $freeDeliveryPromotion,
        ]);
    }

    /**
     * @Route("/admin/promotions/{id}/coupons/new", name="admin_new_promotion_coupon")
     */
    public function newPromotionCouponAction($id, Request $request)
    {
        $promotion = $this->get('sylius.repository.promotion')->find($id);

        $promotionCoupon = $this->get('sylius.factory.promotion_coupon')->createForPromotion($promotion);

        $form = $this->createForm(PromotionCouponType::class, $promotionCoupon);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $promotionCoupon = $form->getData();
            $promotion->addCoupon($promotionCoupon);

            $this->get('sylius.manager.promotion')->flush();

            return $this->redirectToRoute('admin_promotions');
        }

        return $this->render('@App/admin/promotion_coupon.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/promotions/credit-notes/new", name="admin_new_credit_note")
     */
    public function newCreditNoteAction(Request $request)
    {
        $form = $this->createForm(CreditNoteType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();

            $promotion = $this->get('sylius.factory.promotion')->createNew();
            $promotion->setName($data['name']);
            $promotion->setCouponBased(true);
            $promotion->setCode(Uuid::uuid4()->toString());
            $promotion->setPriority(1);

            $promotionAction = new PromotionAction();
            $promotionAction->setType(FixedDiscountPromotionActionCommand::TYPE);
            $promotionAction->setConfiguration([
                'amount' => $data['amount']
            ]);

            $promotion->addAction($promotionAction);

            $promotionRule = $this->get('sylius.factory.promotion_rule')->createNew();
            $promotionRule->setType(IsCustomerRuleChecker::TYPE);
            $promotionRule->setConfiguration([
                'username' => $data['username']
            ]);

            $promotion->addRule($promotionRule);

            do {
                $hash = bin2hex(random_bytes(20));
                $code = strtoupper(substr($hash, 0, 6));
            } while ($this->isUsedCouponCode($code));

            $promotionCoupon = $this->get('sylius.factory.promotion_coupon')->createNew();
            $promotionCoupon->setCode($code);
            $promotionCoupon->setPerCustomerUsageLimit(1);

            $promotion->addCoupon($promotionCoupon);

            $this->get('sylius.repository.promotion')->add($promotion);

            return $this->redirectToRoute('admin_promotions');
        }

        return $this->render('@App/admin/promotion_credit_note.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function isUsedCouponCode(string $code): bool
    {
        return null !== $this->get('sylius.repository.promotion_coupon')->findOneBy(['code' => $code]);
    }

    /**
     * @Route("/admin/promotions/{id}/coupons/{code}", name="admin_promotion_coupon")
     */
    public function promotionCouponAction($id, $code, Request $request)
    {
        $promotionCoupon = $this->get('sylius.repository.promotion_coupon')->findOneByCode($code);
        $promotion = $this->get('sylius.repository.promotion')->find($id);

        $form = $this->createForm(PromotionCouponType::class, $promotionCoupon);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $this->get('sylius.manager.promotion_coupon')->flush();

            return $this->redirectToRoute('admin_promotions');
        }

        return $this->render('@App/admin/promotion_coupon.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/orders/{id}/emails", name="admin_order_email_preview")
     */
    public function orderEmailPreviewAction($id, Request $request, EmailManager $emailManager)
    {
        $order = $this->container->get('sylius.repository.order')->find($id);

        if (!$order) {
            throw $this->createNotFoundException(sprintf('Order #%d does not exist', $id));
        }

        $method = 'createOrderCreatedMessageForCustomer';
        if ($request->query->has('method')) {
            $method = $request->query->get('method');
        }

        $message = call_user_func_array([$emailManager, $method], [$order]);

        $response = new Response();
        $response->setContent($message->getBody());

        return $response;
    }

    /**
     * @Route("/admin/tasks/{id}/emails", name="admin_task_email_preview")
     */
    public function taskEmailPreviewAction($id, Request $request, EmailManager $emailManager)
    {
        $task = $this->getDoctrine()->getRepository(Task::class)->find($id);

        if (!$task) {
            throw $this->createNotFoundException(sprintf('Task #%d does not exist', $id));
        }

        $method = 'createTaskCompletedMessage';
        if ($request->query->has('method')) {
            $method = $request->query->get('method');
        }
        $message = call_user_func_array([$emailManager, $method], [$task]);

        $response = new Response();
        $response->setContent($message->getBody());

        return $response;
    }

    /**
     * @Route("/admin/emails", name="admin_email_preview")
     */
    public function emailsPreviewAction(Request $request, MessageLoggingTwigSwiftMailer $mailer)
    {
        $method = 'sendConfirmationEmailMessage';
        if ($request->query->has('method')) {
            $method = $request->query->get('method');
        }

        $this->getUser()->setConfirmationToken('123456');

        call_user_func_array([$mailer, $method], [$this->getUser()]);

        $messages = $mailer->getMessages();
        $message = current($messages);

        $response = new Response();
        $response->setContent($message->getBody());

        return $response;
    }

    /**
     * @Route("/admin/restaurants/pledges", name="admin_restaurants_pledges")
     */
    public function restaurantsPledgesListAction(Request $request, ObjectManager $manager)
    {
        $pledges = $this->getDoctrine()->getRepository(Pledge::class)->findAll();

        if ($request->isMethod('POST')) {
            $id = $request->request->get('pledge');
            $pledge = $this->getDoctrine()->getRepository(Pledge::class)->find($id);
            if ($request->request->has('accept')) {
                $restaurant = $pledge->accept();
                $manager->persist($restaurant);
                $manager->flush();

                return $this->redirectToRoute('admin_restaurant', [
                    'id' => $restaurant->getId()
                ]);
            }
            if ($request->request->has('reject')) {
                $pledge->setState('refused');
                $manager->flush();
                return $this->redirectToRoute('admin_restaurants_pledges');
            }
        }

        return $this->render('@App/admin/restaurant_pledges.html.twig', [
            'pledges' => $pledges,
        ]);
    }

    /**
     * @Route("/admin/restaurants/pledges/{id}/emails", name="admin_pledge_email_preview")
     */
    public function pledgeEmailPreviewAction($id, Request $request, EmailManager $emailManager)
    {
        $pledge = $this->getDoctrine()->getRepository(Pledge::class)->find($id);

        if (!$pledge) {
            throw $this->createNotFoundException(sprintf('Pledge #%d does not exist', $id));
        }

        $method = 'createAdminPledgeConfirmationMessage';
        if ($request->query->has('method')) {
            $method = $request->query->get('method');
        }
        $message = call_user_func_array([$emailManager, $method], [$pledge]);

        $response = new Response();
        $response->setContent($message->getBody());

        return $response;
    }

    /**
     * @Route("/admin/settings/time-slots", name="admin_time_slots")
     */
    public function timeSlotsAction(Request $request)
    {
        $timeSlots = $this->getDoctrine()->getRepository(TimeSlot::class)->findAll();

        return $this->render('@App/admin/time_slots.html.twig', [
            'time_slots' => $timeSlots,
        ]);
    }

    private function renderTimeSlotForm(Request $request, TimeSlot $timeSlot, ObjectManager $objectManager)
    {
        $form = $this->createForm(TimeSlotType::class, $timeSlot);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $objectManager->persist($timeSlot);
            $objectManager->flush();

            return $this->redirectToRoute('admin_time_slots');
        }

        return $this->render('@App/admin/time_slot.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/settings/time-slots/new", name="admin_new_time_slot")
     */
    public function newTimeSlotAction(Request $request, ObjectManager $objectManager)
    {
        $timeSlot = new TimeSlot();

        return $this->renderTimeSlotForm($request, $timeSlot, $objectManager);
    }

    /**
     * @Route("/admin/settings/time-slots/{id}", name="admin_time_slot")
     */
    public function timeSlotAction($id, Request $request, ObjectManager $objectManager)
    {
        $timeSlot = $this->getDoctrine()->getRepository(TimeSlot::class)->find($id);

        if (!$timeSlot) {
            throw $this->createNotFoundException(sprintf('Time slot #%d does not exist', $id));
        }

        return $this->renderTimeSlotForm($request, $timeSlot, $objectManager);
    }

    /**
     * @Route("/admin/settings/packages", name="admin_packages")
     */
    public function packageSetsAction(Request $request)
    {
        $packageSets = $this->getDoctrine()->getRepository(PackageSet::class)->findAll();

        return $this->render('@App/admin/package_sets.html.twig', [
            'package_sets' => $packageSets,
        ]);
    }

    private function renderPackageSetForm(Request $request, PackageSet $packageSet, ObjectManager $objectManager)
    {
        $form = $this->createForm(PackageSetType::class, $packageSet);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $objectManager->persist($packageSet);
            $objectManager->flush();

            return $this->redirectToRoute('admin_packages');
        }

        return $this->render('@App/admin/package_set.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/settings/packages/new", name="admin_new_package")
     */
    public function newPackageSetAction(Request $request, ObjectManager $objectManager)
    {
        $packageSet = new PackageSet();

        return $this->renderPackageSetForm($request, $packageSet, $objectManager);
    }

    /**
     * @Route("/admin/settings/packages/{id}", name="admin_package")
     */
    public function packageSetAction($id, Request $request, ObjectManager $objectManager)
    {
        $packageSet = $this->getDoctrine()->getRepository(PackageSet::class)->find($id);

        if (!$packageSet) {
            throw $this->createNotFoundException(sprintf('Package set #%d does not exist', $id));
        }

        return $this->renderPackageSetForm($request, $packageSet, $objectManager);
    }
}
