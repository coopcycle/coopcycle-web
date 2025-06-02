<?php

namespace AppBundle\Controller;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class RestaurantMercadopagoController extends AbstractController
{
    #[Route(path: '/restaurant/{id}/mercadopago-account', name: 'restaurant-mercadopago-account', methods: ['GET'])]
    public function getMercadopagoAccount($id, EntityManagerInterface $entityManager)
    {
        $restaurant = $entityManager
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
