<?php

namespace AppBundle\Controller;

use AppBundle\Utils\Cart;
use AppBundle\Entity\Restaurant;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;
use Symfony\Component\HttpFoundation\JsonResponse;

class AdminController extends Controller
{
    use DoctrineTrait;

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

        $limit = 20;

        $pages = ceil($countAll / $limit);
        $page = $request->query->get('p', 1);

        $offset = $limit * ($page - 1);

        $orders = $orderRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);

        return array(
            'page' => $page,
            'pages' => $pages,
            'orders' => $orders,
        );
    }

    /**
     * @Route("/admin/orders/{id}", name="admin_order")
     * @Template
     */
    public function orderAction($id, Request $request)
    {
        $order = $users = $this->getDoctrine()
            ->getRepository('AppBundle:Order')
            ->find($id);

        $events = $users = $this->getDoctrine()
            ->getRepository('AppBundle:OrderEvent')
            ->findBy(['order' => $order], ['createdAt' => 'DESC']);

        return array(
            'order' => $order,
            'events' => $events,
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
            ->findBy([], ['id' => 'DESC'], 10);

        return array(
            'users' => $users,
        );
    }
}
