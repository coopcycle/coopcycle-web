<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\AdminDashboardTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\LocalBusinessTrait;
use AppBundle\Controller\Utils\OrderTrait;
use AppBundle\Controller\Utils\RestaurantTrait;
use AppBundle\Controller\Utils\StoreTrait;
use AppBundle\Controller\Utils\TaskTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Form\RegistrationType;
use AppBundle\Form\RestaurantAdminType;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Store;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Task;
use AppBundle\Entity\Zone;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Form\EmbedSettingsType;
use AppBundle\Form\PricingRuleSetType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\GeoJSONUploadType;
use AppBundle\Form\MaintenanceType;
use AppBundle\Form\OrderType;
use AppBundle\Form\SettingsType;
use AppBundle\Form\StripeLivemodeType;
use AppBundle\Form\TaxationType;
use AppBundle\Form\ZoneCollectionType;
use AppBundle\Service\DeliveryPricingManager;
use AppBundle\Sylius\Order\OrderTransitions;
use Doctrine\Common\Collections\ArrayCollection;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Taxation\Model\TaxCategory;
use Sylius\Component\Taxation\Model\TaxRate;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    use TaskTrait;
    use UserTrait;

    protected function getRestaurantRoutes()
    {
        return [
            'restaurant' => 'admin_restaurant',
            'menu_taxons' => 'admin_restaurant_menu_taxons',
            'menu_taxon' => 'admin_restaurant_menu_taxon',
            'products' => 'admin_restaurant_products',
            'product_options' => 'admin_restaurant_product_options',
            'dashboard' => 'admin_restaurant_dashboard',
            'planning' => 'admin_restaurant_planning',
            'stripe_oauth_redirect' => 'admin_restaurant_stripe_oauth_redirect',
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
            ->add('where', $qb->expr()->orX(
                $qb->expr()->andX(
                    $qb->expr()->isNotNull('o.restaurant'),
                    $qb->expr()->neq('o.state', ':state_cart')
                ),
                $qb->expr()->isNull('o.restaurant')
            ))
            ->setParameter('state_cart', OrderInterface::STATE_CART);

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
    public function orderAction($id, Request $request)
    {
        // Allow retrieving deleted entities anyway
        $this->getDoctrine()->getManager()->getFilters()->disable('soft_deleteable');

        $orderManager = $this->get('coopcycle.order_manager');

        $order = $this->container->get('sylius.repository.order')->find($id);

        if (!$order) {
            throw $this->createNotFoundException(sprintf('Order #%d does not exist', $id));
        }

        $form = $this->createForm(OrderType::class, $order);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->getClickedButton()) {

                if ('accept' === $form->getClickedButton()->getName()) {
                    $orderManager->accept($order);
                }

                if ('fulfill' === $form->getClickedButton()->getName()) {
                    $orderManager->fulfill($order);
                }

                if ('cancel' === $form->getClickedButton()->getName()) {
                    $orderManager->cancel($order);
                }

                foreach ($order->getPayments() as $payment) {
                    if (sprintf('payment_%d_complete', $payment->getId()) === $form->getClickedButton()->getName()) {
                        $orderManager->completePayment($payment);
                    }
                    if (sprintf('payment_%d_refund', $payment->getId()) === $form->getClickedButton()->getName()) {
                        $orderManager->refundPayment($payment);
                    }
                }

                $this->get('sylius.manager.order')->flush();

                return $this->redirectToRoute('admin_orders');
            }
        }

        $pickupAddress = null;
        $dropoffAddress = null;

        if ($order->isFoodtech()) {
            $pickupAddress = $order->getRestaurant()->getAddress();
            $dropoffAddress = $order->getShippingAddress();
        } elseif (null !== $order->getDelivery()) {
            $pickupAddress = $order->getDelivery()->getPickup()->getAddress();
            $dropoffAddress = $order->getDelivery()->getDropoff()->getAddress();
        }

        return $this->render('@App/order/service.html.twig', [
            'layout' => '@App/admin.html.twig',
            'order' => $order,
            'pickup_address' => $pickupAddress,
            'dropoff_address' => $dropoffAddress,
            'form' => $form->createView(),
        ]);
    }

    public function orderListAction(Request $request)
    {
        return $this->baseOrderListAction($request);
    }

    public function foodtechDashboardAction($date, Request $request)
    {
        $date = new \DateTime($date);

        $orders = $this->get('sylius.repository.order')->findByShippedAt($date);

        $ordersNormalized = $this->get('api_platform.serializer')->normalize($orders, 'jsonld', [
            'resource_class' => Order::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['order', 'place']
        ]);

        return $this->render('@App/admin/foodtech_dashboard.html.twig', [
            'orders' => $orders,
            'date' => $date,
            'orders_normalized' => $ordersNormalized,
            'routes' => $request->attributes->get('routes'),
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
        $repository = $this->getDoctrine()->getRepository(Task::class);

        // @link https://symfony.com/doc/current/bundles/FOSUserBundle/user_manager.html
        $userManager = $this->get('fos_user.user_manager');

        $couriers = array_filter($userManager->findUsers(), function (UserInterface $user) {
            return $user->hasRole('ROLE_COURIER');
        });

        usort($couriers, function (UserInterface $a, UserInterface $b) {
            return $a->getUsername() < $b->getUsername() ? -1 : 1;
        });

        $tasks = $this->get('knp_paginator')->paginate(
            $repository->createQueryBuilder('t')->orderBy('t.doneAfter', 'DESC'),
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        return [
            'couriers' => $couriers,
            'tasks' => $tasks,
            'routes' => $this->getDeliveryRoutes(),
        ];
    }

    public function newDeliveryAction(Request $request)
    {
        $delivery = Delivery::createWithDefaults();

        return $this->renderDeliveryForm($delivery, $request);
    }

    public function editDeliveryAction($id, Request $request)
    {
        $delivery = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->find($id);

        return $this->renderDeliveryForm($delivery, $request);
    }

    protected function getDeliveryRoutes()
    {
        return [
            'list'     => 'admin_deliveries',
            'pick'     => 'admin_delivery_pick',
            'deliver'  => 'admin_delivery_deliver',
            'view'     => 'admin_delivery'
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
    public function newTaxationAction(Request $request)
    {
        $slugify = $this->get('slugify');

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
    public function tagsAction(Request $request)
    {
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
     * @Route("/admin/tasks/{id}", name="admin_task")
     */
    public function taskAction($id, Request $request)
    {
        $taskManager = $this->get('coopcycle.task_manager');

        $task = $this->getDoctrine()
            ->getRepository(Task::class)
            ->find($id);

        $form = $this->createTaskEditForm($task);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $task = $form->getData();

            if ($form->getClickedButton() && 'delete' === $form->getClickedButton()->getName()) {

                try {
                    $taskManager->cancel($task);
                } catch (\Exception $e) {
                    // TODO Add form error
                }

                $this->getDoctrine()
                    ->getManagerForClass(Task::class)
                    ->flush();

                // This will be used by the JavaScript modal
                if ($request->headers->has('Accept')
                &&  $request->headers->get('Accept') === 'application/json') {
                    return new Response('', 204);
                }

                return $this->redirect($request->headers->get('referer'));
            }

            if ($form->has('complete')) {
                if ($form->get('complete')->getClickedButton()) {
                    $notes = $form->get('complete')->get('notes')->getData();
                    try {
                        if ('done' === $form->get('complete')->getClickedButton()->getName()) {
                            $taskManager->markAsDone($task, $notes);
                        }
                        if ('fail' === $form->get('complete')->getClickedButton()->getName()) {
                            $taskManager->markAsFailed($task, $notes);
                        }
                    } catch (PreviousTaskNotCompletedException $e) {

                        $message = $this->get('translator')->trans('task.previous_task_not_completed_yet');

                        $form->addError(new FormError($message));

                        return $this->render('@App/admin/task_modal_content.html.twig', [
                            'form' => $form->createView(),
                            'task' => $task
                        ]);
                    }
                }
            }

            $user = $form->get('assign')->getData();

            if (null === $user) {
                $task->unassign();
            } else {
                $task->assignTo($user);
            }

            $this->getDoctrine()
                ->getManagerForClass(Task::class)
                ->flush();

            $taskNormalized = $this->get('api_platform.serializer')->normalize($task, 'jsonld', [
                'resource_class' => Task::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task', 'delivery', 'place']
            ]);

            return new JsonResponse($taskNormalized);
        }

        return $this->render('@App/admin/task_modal_content.html.twig', [
            'form' => $form->createView(),
            'task' => $task
        ]);
    }

    /**
     * @Route("/admin/settings", name="admin_settings")
     * @Template()
     */
    public function settingsAction(Request $request)
    {
        $settingsManager = $this->get('coopcycle.settings_manager');
        $redis = $this->get('snc_redis.default');

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
                    $redis->set('maintenance', '1');
                }
                if ('disable' === $maintenanceForm->getClickedButton()->getName()) {
                    $redis->del('maintenance');
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
            'form' => $form->createView(),
            'maintenance_form' => $maintenanceForm->createView(),
            'maintenance' => $redis->get('maintenance'),
            'stripe_livemode' => $isStripeLivemode,
            'stripe_livemode_form' => $stripeLivemodeForm->createView(),
            'can_enable_stripe_livemode' => $canEnableStripeLivemode,
        ];
    }

    /**
     * @Route("/admin/embed", name="admin_embed")
     * @Template()
     */
    public function embedAction(Request $request)
    {
        $pricingRuleSet = null;
        try {
            $pricingRuleSetId = $this->get('craue_config')->get('embed.delivery.pricingRuleSet');
            if ($pricingRuleSetId) {
                $pricingRuleSet = $this->getDoctrine()
                    ->getRepository(PricingRuleSet::class)
                    ->find($pricingRuleSetId);
            }
        } catch (\RuntimeException $e) {}

        $embedSettingsForm = $this->createForm(EmbedSettingsType::class);
        $embedSettingsForm->get('pricingRuleSet')->setData($pricingRuleSet);

        $embedSettingsForm->handleRequest($request);
        if ($embedSettingsForm->isSubmitted() && $embedSettingsForm->isValid()) {

            $pricingRuleSet = $embedSettingsForm->get('pricingRuleSet')->getData();

            $configEntityClass = $this->getParameter('craue_config.entity_name');

            $setting = $this->getDoctrine()
                ->getRepository($configEntityClass)
                ->findOneBy([
                    'section' => 'embed',
                    'name' => 'embed.delivery.pricingRuleSet'
                ]);

            if (!$setting) {
                $setting = new $configEntityClass();
                $setting->setSection('embed');
                $setting->setName('embed.delivery.pricingRuleSet');

                $this->getDoctrine()
                    ->getManagerForClass($configEntityClass)
                    ->persist($setting);
            }

            $setting->setValue($pricingRuleSet ? $pricingRuleSet->getId() : null);

            $this->getDoctrine()
                ->getManagerForClass($configEntityClass)->flush();

            return $this->redirect($request->headers->get('referer'));
        }

        return [
            'pricing_rule_set' => $pricingRuleSet,
            'embed_settings_form' => $embedSettingsForm->createView(),
        ];
    }

    public function newOrderAction(Request $request)
    {
        $order = $this->get('sylius.factory.order')->createNew();

        $form = $this->createForm(OrderType::class, $order);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $customer = $form->get('customer')->getData();

            var_dump(get_class($customer));
            var_dump($customer->getUsername());
            exit;
        }

        return $this->render('@App/Admin/newOrder.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
