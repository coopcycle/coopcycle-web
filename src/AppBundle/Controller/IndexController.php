<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;

class IndexController extends Controller
{
    /**
     * @Route("/", methods={"GET"})
     * @Template()
     */
    public function indexAction()
    {
        return array();
    }

    /**
     * @Route("/", methods={"POST"})
     * @Template()
     */
    public function searchAction(Request $request)
    {
        $latitude = $request->request->get('latitude');
        $longitude = $request->request->get('longitude');
        $address = $request->request->get('address');

        $geotools = new Geotools();
        $coords = new Coordinate("{$latitude}, {$longitude}");

        $encoded = $geotools->geohash()->encode($coords);
        $geohash = $encoded->getGeohash();

        $request->getSession()->set('geohash', $geohash);
        $request->getSession()->set('address', $address);

        return $this->redirectToRoute('restaurants', array('geohash' => $geohash));
    }
}
