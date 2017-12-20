<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Form\RestaurantAdminType;
use AppBundle\Utils\Cart;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Menu;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Zone;
use AppBundle\Form\DeliveryType;
use AppBundle\Form\MenuCategoryType;
use AppBundle\Form\PricingRuleSetType;
use AppBundle\Form\RestaurantMenuType;
use AppBundle\Form\RestaurantType;
use AppBundle\Form\UpdateProfileType;
use AppBundle\Form\GeoJSONUploadType;
use AppBundle\Form\ZoneCollectionType;
use AppBundle\Service\DeliveryPricingManager;
use AppBundle\Utils\PricingRuleSet;
use Doctrine\Common\Collections\ArrayCollection;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class AdminController extends Controller
{
    const ITEMS_PER_PAGE = 20;

    use AccessControlTrait;
    use DeliveryTrait;
    use AdminTrait;
    use RestaurantTrait;

    /**
     * @Route("/admin", name="admin_index")
     * @Template("@App/Admin/dashboard.html.twig")
     */
    public function indexAction(Request $request)
    {
        return array();
    }

    /**
     * @Route("/admin/orders", name="admin_orders")
     */
    public function ordersAction(Request $request)
    {
        $response = new Response();
        $orderRepository = $this->getDoctrine()->getRepository(Order::class);

        $showCanceled = false;
        if ($request->query->has('show_canceled')) {
            $showCanceled = $request->query->getBoolean('show_canceled');
            $response->headers->setCookie(new Cookie('__show_canceled', $showCanceled ? 'on' : 'off'));
        } elseif ($request->cookies->has('__show_canceled')) {
            $showCanceled = $request->cookies->getBoolean('__show_canceled');
        }

        $statusList = [
            Order::STATUS_WAITING,
            Order::STATUS_ACCEPTED,
            Order::STATUS_REFUSED,
            Order::STATUS_READY,
        ];
        if ($showCanceled) {
            $statusList[] = Order::STATUS_CANCELED;
        }

        $countAll = $orderRepository->countByStatus($statusList);

        $pages = ceil($countAll / self::ITEMS_PER_PAGE);
        $page = $request->query->get('p', 1);

        $offset = self::ITEMS_PER_PAGE * ($page - 1);

        $orders = $orderRepository->findByStatus($statusList, [
            'updatedAt' => 'DESC',
            'createdAt' => 'DESC'
        ], self::ITEMS_PER_PAGE, $offset);

        // $waiting = $orderRepository->countByStatus(Order::STATUS_WAITING);
        // $accepted = $orderRepository->countByStatus(Order::STATUS_ACCEPTED);
        // $ready = $orderRepository->countByStatus(Order::STATUS_READY);

        return $this->render('@App/Admin/orders.html.twig', [
            'page' => $page,
            'pages' => $pages,
            'orders' => $orders,
            // 'waiting_count' => $waiting,
            // 'accepted_count' => $accepted,
            // 'ready_count' => $ready,
            'pdf_route' => 'admin_order_invoice',
            'restaurant_route' => 'admin_restaurant',
            'show_buttons' => true,
            'show_canceled' => $showCanceled,
        ], $response);
    }

    /**
     * @Route("/admin/orders/{id}.pdf", name="admin_order_invoice", requirements={"id" = "\d+"})
     */
    public function orderInvoiceAction($id, Request $request)
    {
        $order = $this->getDoctrine()
            ->getRepository(Order::class)
            ->find($id);

        $this->accessControl($order);

        return $this->invoiceAsPdfAction($order);
    }

    /**
     * @Route("/admin/orders/{id}", name="admin_order")
     * @Template("@App/Order/details.html.twig")
     */
    public function orderAction($id, Request $request)
    {
        $order = $this->getDoctrine()
            ->getRepository('AppBundle:Order')->find($id);

        $orderEvents = [];
        foreach ($order->getEvents() as $event) {
            $orderEvents[] = [
                'status' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        $deliveryEvents = [];
        foreach ($order->getDelivery()->getEvents() as $event) {
            $deliveryEvents[] = [
                'status' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        return array(
            'order' => $order,
            'order_json' => $this->get('serializer')->serialize($order, 'jsonld'),
            'order_events_json' => $this->get('serializer')->serialize($orderEvents, 'json'),
            'delivery_events_json' => $this->get('serializer')->serialize($deliveryEvents, 'json'),
            'layout' => 'AppBundle::admin.html.twig',
            'breadcrumb_path' => 'admin_orders'
        );
    }

    /**
     * @Route("/admin/dashboard", name="admin_dashboard")
     * @Template
     */
    public function dashboardAction(Request $request)
    {
        return array();
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

        $editForm = $this->createForm(UpdateProfileType::class, $user, [
            'with_restaurants' => true
        ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $userManager = $this->getDoctrine()->getManagerForClass(ApiUser::class);
            $userManager->persist($user);
            $userManager->flush();

            return $this->redirectToRoute('admin_user_details', ['username' => $user->getUsername()]);
        }

        return [
            'form' => $editForm->createView(),
            'user' => $user,
        ];
    }

    /**
     * @Route("/admin/restaurants", name="admin_restaurants")
     * @Template
     */
    public function restaurantsAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('AppBundle:Restaurant');

        $countAll = $repository
            ->createQueryBuilder('r')->select('COUNT(r)')
            ->getQuery()->getSingleScalarResult();

        $pages = ceil($countAll / self::ITEMS_PER_PAGE);
        $page = $request->query->get('p', 1);

        $offset = self::ITEMS_PER_PAGE * ($page - 1);

        $restaurants = $repository->findBy([], [
            'id' => 'DESC',
        ], self::ITEMS_PER_PAGE, $offset);

        return array(
            'restaurants' => $restaurants,
            'page' => $page,
            'pages' => $pages,
        );
    }

    /**
     * @Route("/admin/restaurant/{id}", name="admin_restaurant")
     * @Template("@App/Restaurant/form.html.twig")
     */
    public function restaurantAction($id, Request $request)
    {
        return $this->editRestaurantAction($id, $request, 'AppBundle::admin.html.twig', [
            'success' => 'admin_restaurant',
            'restaurants' => 'admin_restaurants',
            'menu' => 'admin_restaurant_menu',
            'orders' => 'admin_restaurant_orders',
        ], RestaurantAdminType::class);
    }

    /**
     * @Route("/admin/restaurants/new", name="admin_restaurant_new")
     * @Template("@App/Restaurant/form.html.twig")
     */
    public function newRestaurantAction(Request $request)
    {
        return $this->editRestaurantAction(null, $request, 'AppBundle::admin.html.twig', [
            'success' => 'admin_restaurants',
            'restaurants' => 'admin_restaurants',
            'menu' => 'admin_restaurant_menu',
            'orders' => 'admin_restaurant_orders',
        ], RestaurantAdminType::class);
    }

    /**
     * @Route("/admin/restaurant/{id}/menu", name="admin_restaurant_menu")
     * @Template("@App/Restaurant/form-menu.html.twig")
     */
    public function restaurantMenuAction($id, Request $request)
    {
        return $this->editMenuAction($id, $request, 'AppBundle::admin.html.twig', [
            'success' => 'admin_restaurant_menu',
            'restaurants' => 'admin_restaurants',
            'restaurant' => 'admin_restaurant',
            'add_section' => 'admin_restaurant_menu_add_section'
        ]);
    }

    /**
     * @Route("/admin/restaurant/{id}/menu/add-section", name="admin_restaurant_menu_add_section")
     * @Template("@App/Restaurant/form-menu.html.twig")
     */
    public function addMenuSectionAction($id, Request $request)
    {
        $request->attributes->set('_add_menu_section', true);

        return $this->editMenuAction($id, $request, 'AppBundle::admin.html.twig', [
            'success' => 'admin_restaurants',
            'restaurants' => 'admin_restaurants',
            'restaurant' => 'admin_restaurant',
            'add_section' => 'admin_restaurant_menu_add_section'
        ]);
    }

    /**
     * @Route("/admin/deliveries", name="admin_deliveries")
     * @Template()
     */
    public function deliveriesAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('AppBundle:Delivery');

        // @link https://symfony.com/doc/current/bundles/FOSUserBundle/user_manager.html
        $userManager = $this->get('fos_user.user_manager');

        $couriers = array_filter($userManager->findUsers(), function (UserInterface $user) {
            return $user->hasRole('ROLE_COURIER');
        });

        usort($couriers, function (UserInterface $a, UserInterface $b) {
            return $a->getUsername() < $b->getUsername() ? -1 : 1;
        });

        return [
            'couriers' => $couriers,
            'deliveries' => $repository->findBy([], ['date' => 'DESC']),
            'routes' => $this->getDeliveryRoutes(),
        ];
    }

    /**
     * @Route("/admin/deliveries/new", name="admin_deliveries_new")
     * @Template()
     */
    public function newDeliveryAction(Request $request)
    {
        $delivery = new Delivery();

        $form = $this->createForm(DeliveryType::class, $delivery);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $delivery = $form->getData();

            $em = $this->getDoctrine()->getManagerForClass('AppBundle:Delivery');

            if ($delivery->getDate() < new \DateTime()) {
                $form->get('date')->addError(new FormError('The date is in the past'));
            }

            if ($form->isValid()) {

                $this->get('delivery_service.default')->calculate($delivery);
                $this->get('coopcycle.delivery.manager')->applyTaxes($delivery);

                $em->persist($delivery);
                $em->flush();

                return $this->redirectToRoute('admin_deliveries');
            }
        }

        return [
            'google_api_key' => $this->getParameter('google_api_key'),
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/admin/restaurants/{restaurantId}/orders", name="admin_restaurant_orders")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function restaurantOrdersAction($restaurantId, Request $request)
    {
        return $this->restaurantDashboard($restaurantId, null, $request, [
            'order_accept'      => 'admin_order_accept',
            'order_refuse'      => 'admin_order_refuse',
            'order_cancel'      => 'admin_order_cancel',
            'order_ready'       => 'admin_order_ready',
            'order_details'     => 'admin_order',
            'user_details'      => 'user',
            'restaurant'        => 'admin_restaurant',
            'restaurants'       => 'admin_restaurants',
            'restaurant_order'  => 'admin_restaurant_order',
            'restaurant_orders' => 'admin_restaurant_orders'
        ]);
    }

    /**
     * @Route("/admin/restaurants/{restaurantId}/orders/{orderId}", name="admin_restaurant_order")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function restaurantOrderAction($restaurantId, $orderId, Request $request)
    {
        return $this->restaurantDashboard($restaurantId, $orderId, $request, [
            'order_accept'      => 'admin_order_accept',
            'order_refuse'      => 'admin_order_refuse',
            'order_cancel'      => 'admin_order_cancel',
            'order_ready'       => 'admin_order_ready',
            'order_details'     => 'admin_order',
            'user_details'      => 'user',
            'restaurant'        => 'admin_restaurant',
            'restaurants'       => 'admin_restaurants',
            'restaurant_order'  => 'admin_restaurant_order',
            'restaurant_orders' => 'admin_restaurant_orders'
        ]);
    }

    /**
     * @Route("/admin/orders/{id}/accept", name="admin_order_accept")
     * @Template
     */
    public function acceptOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->acceptOrder($id, 'admin_restaurant_order', [
                'restaurantId' => $order->getRestaurant()->getId(),
                'orderId' => $order->getId(),
            ]);
        }
    }

    /**
     * @Route("/admin/orders/{id}/refuse", name="admin_order_refuse")
     * @Template
     */
    public function refuseOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->refuseOrder($id, 'admin_restaurant_order', [
                'restaurantId' => $order->getRestaurant()->getId(),
                'orderId' => $order->getId(),
            ]);
        }
    }

    /**
     * @Route("/admin/orders/{id}/ready", name="admin_order_ready")
     * @Template
     */
    public function readyOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->readyOrder($id, 'admin_restaurant_order', [
                'restaurantId' => $order->getRestaurant()->getId(),
                'orderId' => $order->getId(),
            ]);
        }
    }

    /**
     * @Route("/admin/orders/{id}/cancel", name="admin_order_cancel")
     */
    public function cancelOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            return $this->cancelOrder($id, 'admin_orders');
        }
    }

    /**
     * @Route("/admin/deliveries/{id}/dispatch", methods={"POST"}, name="admin_delivery_dispatch")
     */
    public function dispatchDeliveryAction($id, Request $request)
    {
        $delivery = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->find($id);

        $this->accessControl($delivery);

        $userManager = $this->get('fos_user.user_manager');

        $userId = $request->request->get('courier');
        $courier = $userManager->findUserBy(['id' => $userId]);

        $this->get('coopcycle.delivery.manager')->dispatch($delivery, $courier);

        $this->getDoctrine()
            ->getManagerForClass(Delivery::class)
            ->flush();

        return $this->redirectToRoute('admin_deliveries');
    }

    protected function getDeliveryRoutes()
    {
        return [
            'list'     => 'admin_deliveries',
            'dispatch' => 'admin_delivery_dispatch',
            'pick'     => 'admin_delivery_pick',
            'deliver'  => 'admin_delivery_deliver'
        ];
    }

    /**
     * @Route("/admin/menu/categories", name="admin_menu_categories")
     * @Template
     */
    public function menuCategoriesAction(Request $request)
    {
        $categories = $this->getDoctrine()
            ->getRepository(Menu\MenuCategory::class)
            ->findBy([], ['name' => 'ASC']);

        return [
            'categories' => $categories,
        ];
    }

    /**
     * @Route("/admin/menu/categories/new", name="admin_menu_category_new")
     * @Template
     */
    public function newMenuCategoryAction(Request $request)
    {
        $category = new Menu\MenuCategory();

        $form = $this->createForm(MenuCategoryType::class, $category);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $category = $form->getData();
            $this->getDoctrine()->getManagerForClass(Menu\MenuCategory::class)->persist($category);
            $this->getDoctrine()->getManagerForClass(Menu\MenuCategory::class)->flush();

            return $this->redirectToRoute('admin_menu_categories');
        }

        return [
            'form' => $form->createView(),
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
     * @Route("/admin/deliveries/pricing", name="admin_deliveries_pricing")
     * @Template
     */
    public function deliveriesPricingAction(Request $request)
    {
        $rules = $this->getDoctrine()
            ->getRepository(Delivery\PricingRule::class)
            ->findBy([], ['position' => 'ASC']);

        $ruleSet = new PricingRuleSet($rules);

        $originalRules = new ArrayCollection();
        foreach ($ruleSet as $rule) {
            $originalRules->add($rule);
        }

        $form = $this->createForm(PricingRuleSetType::class, $ruleSet);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $em = $this->getDoctrine()->getManagerForClass(Delivery\PricingRule::class);

            foreach ($originalRules as $originalRule) {
                if (!$data->contains($originalRule)) {
                    $em->remove($originalRule);
                }
            }

            foreach ($data as $rule) {
                $em->persist($rule);
            }

            $em->flush();

            return $this->redirectToRoute('admin_deliveries_pricing');
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/admin/deliveries/pricing/calculate", name="admin_deliveries_pricing_calculate")
     * @Template
     */
    public function deliveriesPricingCalculateAction(Request $request)
    {
        $deliveryManager = $this->get('coopcycle.delivery.manager');

        $delivery = new Delivery();
        $delivery->setDistance($request->query->get('distance'));

        return new JsonResponse($deliveryManager->getPrice($delivery));
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

        $geojson = new \stdClass();
        $geojson->features = [];

        $uploadForm = $this->createForm(GeoJSONUploadType::class, $geojson);
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
            foreach ($geojson->features as $feature) {
                $zone = new Zone();
                $zone->setGeoJSON($feature['geometry']);
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
}
