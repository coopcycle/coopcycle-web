<?php

namespace AppBundle\Controller;

use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Entity\Restaurant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class IndexController extends AbstractController
{
    use UserTrait;

    const MAX_RESULTS = 3;

    /**
     * @Template
     * @HideSoftDeleted
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
            'addresses_normalized' => $this->getUserAddresses(),
        );
    }
}
