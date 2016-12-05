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
     * @Route("/admin/orders", methods={"GET"})
     * @Template()
     */
    public function ordersAction(Request $request)
    {
        $orders = $this->getRepository('Order')->findAll();

        return array(
            'orders' => $orders,
        );
    }

    /**
     * @Route("/admin/tracking", methods={"GET"})
     * @Template()
     */
    public function trackingAction(Request $request)
    {
        return array();
    }
}
