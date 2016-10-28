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
    /**
     * @Template()
     */
    public function indexAction(Request $request)
    {
        // $orderManager = $this->getDoctrine()->getManagerForClass('AppBundle\\Entity\\Order');
        // $orderRepository = $orderManager->getRepository('AppBundle\\Entity\\Order');

        // $orders = $orderRepository->findBy(array('customer' => $this->getUser()));

        return array(
            // 'orders' => $orders,
        );
    }
}
