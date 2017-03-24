<?php

namespace AppBundle\Controller;

use AppBundle\Utils\Cart;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Order;
use AppBundle\Form\RestaurantMenuType;
use AppBundle\Form\RestaurantType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
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
        $picked = $orderRepository->countByStatus(Order::STATUS_PICKED);

        return array(
            'page' => $page,
            'pages' => $pages,
            'orders' => $orders,
            'waiting_count' => $waiting,
            'accepted_count' => $accepted,
            'picked_count' => $picked,
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

        $events = [];
        foreach ($order->getEvents() as $event) {
            $events[] = [
                'eventName' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        return array(
            'order' => $order,
            'order_json' => $this->get('serializer')->serialize($order, 'jsonld'),
            'order_events_json' => $this->get('serializer')->serialize($events, 'json'),
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

        $this->get('snc_redis.default')->lrem('orders:waiting', 0, $order->getId());

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
            ->findBy([], ['id' => 'DESC'], 10);

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
     * @Route("/admin/restaurant/{id}/menu", name="admin_restaurant_menu")
     * @Template("@App/Restaurant/form-menu.html.twig")
     */
    public function restaurantMenuAction($id, Request $request)
    {
        return $this->editMenuAction($id, $request, 'AppBundle::admin.html.twig', [
            'success' => 'admin_restaurants',
            'restaurants' => 'admin_restaurants',
            'restaurant' => 'admin_restaurant',
        ]);
    }
}
