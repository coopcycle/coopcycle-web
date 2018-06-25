<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Store;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class StoreController extends Controller
{
    /**
     * @Route("/store/{id}-{slug}", name="store",
     *   requirements={"id" = "(\d+|__STORE_ID__)", "slug" = "([a-z0-9-]+)"},
     *   defaults={"slug" = ""}
     * )
     * @Template()
     */
    public function indexAction($id, $slug, Request $request)
    {

        $store = $this->getDoctrine()
            ->getRepository(Store::class)
            ->findOneBy(['id' => $id]);

        if (!$store) {
            throw new NotFoundHttpException();
        }

        return array(
            'store' => $store,
            'availabilities' => $store->getAvailabilities(),
        );
    }
}