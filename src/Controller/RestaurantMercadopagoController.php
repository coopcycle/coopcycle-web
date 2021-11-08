<?php

namespace AppBundle\Controller;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class RestaurantMercadopagoController extends AbstractController
{

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @Route("/restaurant/{id}/mercadopago-account", name="restaurant-mercadopago-account", methods={"GET"})
     */
    public function getMercadopagoAccount($id)
    {
        $restaurant = $this->getDoctrine()
            ->getRepository(LocalBusiness::class)->find($id);

        $mp_account = $restaurant->getMercadopagoAccount();

        if (null === $mp_account) {
            return new JsonResponse([
                'message' => sprintf('Restaurant with id %d has not a Mercadopago account associated', $id)
            ], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'public_key' => $mp_account->getPublicKey()
        ];

        return new JsonResponse($data, Response::HTTP_OK);
    }

}
