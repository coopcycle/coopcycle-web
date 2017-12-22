<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Order;
use AppBundle\Entity\Delivery;
use AppBundle\Form\AddressType;
use AppBundle\Form\UpdateProfileType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route as Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class ProfileController extends Controller
{
    use AccessControlTrait;
    use DeliveryTrait;
    use AdminTrait;
    use RestaurantTrait;
    use UserTrait;

    /**
     * @Route("/profile/edit", name="profile_edit")
     * @Template()
     *
     * @param Request $request
     * @return array
     */
    public function editProfile(Request $request) {

        $user = $this->getUser();

        $editForm = $this->createForm(UpdateProfileType::class, $user);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $userManager = $this->getDoctrine()->getManagerForClass(ApiUser::class);
            $userManager->persist($user);
            $userManager->flush();

            return $this->redirectToRoute('fos_user_profile_show');
        }

        return array(
            'form' => $editForm->createView()
        );
    }

    /**
     * @Route("/profile/orders", name="profile_orders")
     * @Template()
     */
    public function ordersAction(Request $request)
    {
        $orderManager = $this->getDoctrine()->getManagerForClass('AppBundle:Order');
        $orderRepository = $orderManager->getRepository('AppBundle:Order');

        $page = $request->query->get('page', 1);

        $qb = $orderRepository->createQueryBuilder('o');

        $qb->select($qb->expr()->count('o'))
           ->where('o.customer = ?1')
           ->setParameter(1, $this->getUser());

        $query = $qb->getQuery();
        $ordersCount = $query->getSingleScalarResult();

        $perPage = 15;

        $pages = ceil($ordersCount / $perPage);
        $offset = $perPage * ($page - 1);

        $orders = $orderRepository->findBy(
            ['customer' => $this->getUser()],
            ['createdAt' => 'DESC'],
            $perPage,
            $offset
        );

        return array(
            'orders' => $orders,
            'page' => $page,
            'pages' => $pages,
            'pdf_route' => 'profile_order_invoice',
            'restaurant_route' => 'restaurant',
            'show_buttons' => false,
        );
    }

    /**
     * @Route("/profile/orders/{id}.pdf", name="profile_order_invoice", requirements={"id" = "\d+"})
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
     * @Route("/profile/orders/{id}", name="profile_order")
     * @Template("@App/Order/details.html.twig")
     */
    public function orderAction($id, Request $request)
    {
        $order = $this->getDoctrine()
            ->getRepository(Order::class)->find($id);

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
            'layout' => 'AppBundle::profile.html.twig',
            'breadcrumb_path' => 'profile_orders'
        );
    }

    /**
     * @Route("/profile/addresses", name="profile_addresses")
     * @Template()
     */
    public function addressesAction(Request $request)
    {
        return array(
            'addresses' => $this->getUser()->getAddresses(),
        );
    }

    /**
     * @Route("/profile/addresses/new", name="profile_address_new")
     * @Template()
     */
    public function newAddressAction(Request $request)
    {
        $address = new Address();

        $form = $this->createForm(AddressType::class, $address);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $address = $form->getData();

            $this->getUser()->addAddress($address);

            $manager = $this->getDoctrine()->getManagerForClass(Address::class);
            $manager->persist($address);
            $manager->flush();

            return $this->redirectToRoute('profile_addresses');
        }

        return array(
            'form' => $form->createView(),
        );
    }

    /**
     * @Route("/profile/deliveries", name="profile_courier_deliveries")
     * @Template()
     */
    public function courierDeliveriesAction(Request $request)
    {
        $deliveryTimes = $this->getDoctrine()->getRepository(Delivery::class)
            ->getDeliveryTimes($this->getUser());

        $avgDeliveryTime = $this->getDoctrine()->getRepository(Delivery::class)
            ->getAverageDeliveryTime($this->getUser());

        $deliveries = $this->getDoctrine()->getRepository(Delivery::class)->findBy(
            ['courier' => $this->getUser()],
            ['date' => 'DESC']
        );

        return [
            'deliveries' => $deliveries,
            'routes' => $this->getDeliveryRoutes(),
            'avg_delivery_time' => $avgDeliveryTime,
            'delivery_times' => $deliveryTimes,
        ];
    }

    /**
     * @Route("/profile/restaurants", name="profile_restaurants")
     * @Template()
     */
    public function restaurantsAction(Request $request)
    {
        $restaurants = $this->getUser()->getRestaurants();

        return [
            'restaurants' => $restaurants,
        ];
    }

    /**
     * @Route("/profile/restaurants/new", name="profile_restaurant_new")
     * @Template("@App/Restaurant/form.html.twig")
     */
    public function newRestaurantAction(Request $request)
    {
        return $this->editRestaurantAction(null, $request, 'AppBundle::profile.html.twig', [
            'success' => 'profile_restaurant',
            'restaurants' => 'profile_restaurants',
            'menu' => 'profile_restaurant_menu',
            'orders' => 'profile_restaurant_orders',
            'planning' => 'profile_restaurant_planning'
        ]);
    }

    /**
     * @Route("/profile/restaurants/{id}", name="profile_restaurant")
     * @Template("@App/Restaurant/form.html.twig")
     */
    public function restaurantEditAction($id, Request $request)
    {
        return $this->editRestaurantAction($id, $request, 'AppBundle::profile.html.twig', [
            'success' => 'profile_restaurant',
            'restaurants' => 'profile_restaurants',
            'menu' => 'profile_restaurant_menu',
            'orders' => 'profile_restaurant_orders',
            'planning' => 'profile_restaurant_planning'
        ]);
    }

    /**
     * @Route("/profile/restaurants/{id}/planning", name="profile_restaurant_planning")
     * @Template("@App/Restaurant/planning.html.twig")
     */
    public function restaurantPlanningAction($id, Request $request)
    {
        return $this->editPlanningAction($id, $request, 'AppBundle::profile.html.twig', [
            'restaurants' => 'profile_restaurants',
            'restaurant' => 'profile_restaurant',
            'success' => 'profile_restaurant_planning'
        ]);
    }

    /**
     * @Route("/profile/restaurants/{id}/menu", name="profile_restaurant_menu")
     * @Template("@App/Restaurant/form-menu.html.twig")
     */
    public function restaurantMenuAction($id, Request $request)
    {
        return $this->editMenuAction($id, $request, 'AppBundle::profile.html.twig', [
            'success' => 'profile_restaurants',
            'restaurants' => 'profile_restaurants',
            'restaurant' => 'profile_restaurant',
            'add_section' => 'profile_restaurant_menu_add_section'
        ]);
    }

    /**
     * @Route("/profile/restaurant/{id}/menu/add-section", name="profile_restaurant_menu_add_section")
     * @Template("@App/Restaurant/form-menu.html.twig")
     */
    public function addMenuSectionAction($id, Request $request)
    {
        $request->attributes->set('_add_menu_section', true);

        return $this->editMenuAction($id, $request, 'AppBundle::profile.html.twig', [
            'success' => 'admin_restaurants',
            'restaurants' => 'profile_restaurants',
            'restaurant' => 'profile_restaurant',
            'add_section' => 'profile_restaurant_menu_add_section'
        ]);
    }

    /**
     * @Route("/profile/payment", name="profile_payment")
     * @Template()
     */
    public function paymentAction(Request $request)
    {
        $stripeParams = $this->getUser()->getStripeParams();

        $stripeClientId = $this->getParameter('stripe_connect_client_id');
        $stripeAuthorizeURL = 'https://connect.stripe.com/oauth/authorize?response_type=code&client_id='.$stripeClientId.'&scope=read_write';

        return [
            'stripe_authorize_url' => $stripeAuthorizeURL,
            'stripe_user_id' => $stripeParams ? $stripeParams->getUserId() : null
        ];
    }

    /**
     * @Route("/profile/restaurants/{restaurantId}/orders", name="profile_restaurant_orders")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function restaurantOrdersAction($restaurantId, Request $request)
    {
        return $this->restaurantDashboard($restaurantId, null, $request, [
            'order_accept'      => 'profile_order_accept',
            'order_refuse'      => 'profile_order_refuse',
            'order_cancel'      => 'profile_order_cancel',
            'order_ready'       => 'profile_order_ready',
            'order_details'     => 'profile_order',
            'user_details'      => 'user',
            'restaurant'        => 'profile_restaurant',
            'restaurants'       => 'profile_restaurants',
            'restaurant_order'  => 'profile_restaurant_order',
            'restaurant_orders' => 'profile_restaurant_orders'
        ]);
    }

     /**
     * @Route("/profile/restaurants/{restaurantId}/orders/{orderId}", name="profile_restaurant_order")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function restaurantOrderAction($restaurantId, $orderId, Request $request)
    {
        return $this->restaurantDashboard($restaurantId, $orderId, $request, [
            'order_accept'      => 'profile_order_accept',
            'order_refuse'      => 'profile_order_refuse',
            'order_cancel'      => 'profile_order_cancel',
            'order_ready'       => 'profile_order_ready',
            'order_details'     => 'profile_order',
            'user_details'      => 'user',
            'restaurant'        => 'profile_restaurant',
            'restaurants'       => 'profile_restaurants',
            'restaurant_order'  => 'profile_restaurant_order',
            'restaurant_orders' => 'profile_restaurant_orders'
        ]);
    }

    /**
     * @Route("/profile/orders/{id}/accept", name="profile_order_accept")
     * @Template
     */
    public function acceptOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->acceptOrder($id, 'profile_restaurant_orders', ['restaurantId' => $order->getRestaurant()->getId()]);
        }
    }

    /**
     * @Route("/profile/orders/{id}/refuse", name="profile_order_refuse")
     * @Template
     */
    public function refuseOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->refuseOrder($id, 'profile_restaurant_orders', ['restaurantId' => $order->getRestaurant()->getId()]);
        }
    }

    /**
     * @Route("/profile/orders/{id}/ready", name="profile_order_ready")
     * @Template
     */
    public function readyOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->readyOrder($id, 'profile_restaurant_orders', ['restaurantId' => $order->getRestaurant()->getId()]);
        }
    }

    /**
     * @Route("/profile/orders/{id}/cancel", name="profile_order_cancel")
     */
    public function cancelOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->cancelOrder($id, 'profile_restaurant_orders', ['restaurantId' => $order->getRestaurant()->getId()]);
        }
    }

    protected function getDeliveryRoutes()
    {
        return [
            'list'    => 'profile_courier_deliveries',
            'pick'    => 'profile_delivery_pick',
            'deliver' => 'profile_delivery_deliver'
        ];
    }

    /**
     * @Route("/profile/tracking", name="profile_tracking")
     * @Template("@App/User/tracking.html.twig")
     */
    public function trackingAction(Request $request)
    {
        return $this->userTracking($this->getUser());
    }
}
