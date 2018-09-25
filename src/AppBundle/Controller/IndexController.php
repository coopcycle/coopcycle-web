<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

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
        $user = $this->getUser();

        if ($user) {
            $addresses = $user->getAddresses();
        }
        else {
            $addresses = [];
        }

        $restaurants = $this->getDoctrine()
            ->getRepository(Restaurant::class)
            ->findRandom(self::MAX_RESULTS);

        return array(
            'addresses' => $addresses,
            'restaurants' => $restaurants,
            'max_results' => self::MAX_RESULTS,
        );
    }
}
