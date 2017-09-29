<?php

namespace AppBundle\Controller;

use AppBundle\Utils\Cart;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use AppBundle\Form\DeliveryType;
use AppBundle\Form\RestaurantMenuType;
use AppBundle\Form\RestaurantType;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class AdminController extends Controller
{
    const ITEMS_PER_PAGE = 20;

    use DoctrineTrait;
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
     * @Template
     */
    public function ordersAction(Request $request)
    {
        $orderRepository = $this->getDoctrine()->getRepository('AppBundle:Order');

        $countAll = $orderRepository->countAll();

        $pages = ceil($countAll / self::ITEMS_PER_PAGE);
        $page = $request->query->get('p', 1);

        $offset = self::ITEMS_PER_PAGE * ($page - 1);

        $orders = $orderRepository->findBy([], [
            'updatedAt' => 'DESC',
            'createdAt' => 'DESC'
        ], self::ITEMS_PER_PAGE, $offset);

        $waiting = $orderRepository->countByStatus(Order::STATUS_WAITING);
        $accepted = $orderRepository->countByStatus(Order::STATUS_ACCEPTED);
        $ready = $orderRepository->countByStatus(Order::STATUS_READY);

        return array(
            'page' => $page,
            'pages' => $pages,
            'orders' => $orders,
            'waiting_count' => $waiting,
            'accepted_count' => $accepted,
            'ready_count' => $ready,
        );
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
                'eventName' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        $deliveryEvents = [];
        foreach ($order->getDelivery()->getEvents() as $event) {
            $deliveryEvents[] = [
                'eventName' => $event->getEventName(),
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
     * @Route("/admin/orders/{id}/cancel", name="order_cancel")
     * @Template
     */
    public function orderCancelAction($id, Request $request)
    {
        $em = $this->getDoctrine()->getManagerForClass('AppBundle:Order');

        // TODO Check status = WAITING

        $order = $this->getDoctrine()
            ->getRepository('AppBundle:Order')
            ->find($id);

        $order->setStatus(Order::STATUS_CANCELED);

        $em->flush();

        $this->get('snc_redis.default')->lrem('deliveries:waiting', 0, $order->getDelivery()->getId());

        return $this->redirectToRoute('admin_orders');
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
            'success' => 'admin_restaurants',
            'restaurants' => 'admin_restaurants',
            'menu' => 'admin_restaurant_menu',
        ]);
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
        ]);
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
        ]);
    }

    /**
     * @Route("/admin/deliveries", name="admin_deliveries")
     * @Template()
     */
    public function deliveriesAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('AppBundle:Delivery');

        return [
            'deliveries' => $repository->findBy([], ['date' => 'DESC']),
        ];
    }

    /**
     * @Route("/admin/deliveries/new", name="admin_deliveries_new")
     * @Template()
     */
    public function newDeliveryAction(Request $request)
    {
        $osrmHost = $this->getParameter('osrm_host');

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
                $originLat = $form->get('originAddress')->get('latitude')->getData();
                $originLng = $form->get('originAddress')->get('longitude')->getData();

                $deliveryLat = $form->get('deliveryAddress')->get('latitude')->getData();
                $deliveryLng = $form->get('deliveryAddress')->get('longitude')->getData();

                $data = $this->container->get('routing_service')->getRawResponse(
                    new GeoCoordinates($originLat, $originLng),
                    new GeoCoordinates($deliveryLat, $deliveryLng)
                );

                $delivery->setDistance((int) $data['routes'][0]['distance']);
                $delivery->setDuration((int) $data['routes'][0]['duration']);

                $delivery->getOriginAddress()->setGeo(new GeoCoordinates($originLat, $originLng));
                $delivery->getDeliveryAddress()->setGeo(new GeoCoordinates($deliveryLat, $deliveryLng));

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
     * @Route("/admin/restaurants/{id}/orders", name="admin_restaurant_orders")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function restaurantOrdersAction($id, Request $request)
    {
        $restaurantRepo = $this->getDoctrine()->getRepository('AppBundle:Restaurant');
        $orderRepo = $this->getDoctrine()->getRepository('AppBundle:Order');

        $restaurant = $restaurantRepo->find($id);
        $orders = $orderRepo->getWaitingOrders();

        $this->checkAccess($restaurant);

        $ordersJson = [];
        foreach ($orders as $order) {
            $ordersJson[] = $this->get('serializer')->serialize($order, 'jsonld', ['groups' => ['order']]);
        }

        return [
            'restaurant' => $restaurant,
            'restaurant_json' => $this->get('serializer')->serialize($restaurant, 'jsonld'),
            'orders' => $orders,
            'orders_json' => '[' . implode(',', $ordersJson) . ']',
            'restaurants_route' => 'admin_restaurants',
            'restaurant_route' => 'admin_restaurant',
        ];
    }

    /**
     * @Route("/admin/restaurants/{restaurantId}/orders/{orderId}/accept", name="admin_restaurant_order_accept")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function acceptOrderAction($restaurantId, $orderId, Request $request)
    {
        $restaurantRepo = $this->getDoctrine()->getRepository('AppBundle:Restaurant');
        $orderRepo = $this->getDoctrine()->getRepository(Order::class);

        $restaurant = $restaurantRepo->find($restaurantId);
        $order = $orderRepo->find($orderId);

        $this->checkAccess($restaurant);

        if ($request->isMethod('POST')) {
            $order->setStatus(Order::STATUS_ACCEPTED);
            $this->getDoctrine()->getManagerForClass(Order::class)->flush();
        }

        return $this->redirectToRoute('admin_restaurant_orders', ['id' => $restaurantId]);
    }

    /**
     * @Route("/admin/restaurants/{restaurantId}/orders/{orderId}/refuse", name="admin_restaurant_order_refuse")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function refuseOrderAction($restaurantId, $orderId, Request $request)
    {
        $restaurantRepo = $this->getDoctrine()->getRepository('AppBundle:Restaurant');
        $orderRepo = $this->getDoctrine()->getRepository(Order::class);

        $restaurant = $restaurantRepo->find($restaurantId);
        $order = $orderRepo->find($orderId);

        $this->checkAccess($restaurant);

        if ($request->isMethod('POST')) {
            $order->setStatus(Order::STATUS_REFUSED);
            $this->getDoctrine()->getManagerForClass(Order::class)->flush();
        }

        return $this->redirectToRoute('admin_restaurant_orders', ['id' => $restaurantId]);
    }

    /**
     * @Route("/admin/restaurants/{restaurantId}/orders/{orderId}/cancel", name="admin_restaurant_order_cancel")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function cancelOrderAction($restaurantId, $orderId, Request $request)
    {
        $restaurantRepo = $this->getDoctrine()->getRepository('AppBundle:Restaurant');
        $orderRepo = $this->getDoctrine()->getRepository(Order::class);

        $restaurant = $restaurantRepo->find($restaurantId);
        $order = $orderRepo->find($orderId);

        $this->checkAccess($restaurant);

        if ($request->isMethod('POST')) {
            $order->setStatus(Order::STATUS_CANCELED);
            $this->getDoctrine()->getManagerForClass(Order::class)->flush();
        }

        return $this->redirectToRoute('admin_restaurant_orders', ['id' => $restaurantId]);
    }

    /**
     * @Route("/admin/restaurants/{restaurantId}/orders/{orderId}/ready", name="admin_restaurant_order_ready")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function readyOrderAction($restaurantId, $orderId, Request $request)
    {
        $restaurantRepo = $this->getDoctrine()->getRepository('AppBundle:Restaurant');
        $orderRepo = $this->getDoctrine()->getRepository(Order::class);

        $restaurant = $restaurantRepo->find($restaurantId);
        $order = $orderRepo->find($orderId);

        $this->checkAccess($restaurant);

        if ($request->isMethod('POST')) {
            $order->setStatus(Order::STATUS_READY);
            $this->getDoctrine()->getManagerForClass(Order::class)->flush();
        }

        return $this->redirectToRoute('admin_restaurant_orders', ['id' => $restaurantId]);
    }
}
