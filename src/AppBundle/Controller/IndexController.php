<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class IndexController extends Controller
{
    const MAX_RESULTS = 3;

    /**
     * @Route("/", name="homepage")
     * @Template
     */
    public function indexAction()
    {
        $restaurantRepository = $this->getDoctrine()->getRepository(Restaurant::class);

        $restaurants = $restaurantRepository->findRandom(self::MAX_RESULTS);
        $countAll = $restaurantRepository->countAll();

        $showMore = $countAll > count($restaurants);

        return array(
            'restaurants' => $restaurants,
            'max_results' => self::MAX_RESULTS,
            'show_more' => $showMore,
        );
    }
}
