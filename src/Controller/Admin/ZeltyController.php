<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Integration\Zelty\ZeltyClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ZeltyController extends AbstractController
{
    public function __construct(
        private readonly ZeltyClient $zeltyClient,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/admin/restaurant/{id}/zelty/dishes', name: 'admin_restaurant_zelty_dishes', methods: ['GET'])]
    public function dishes(LocalBusiness $restaurant): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$restaurant->hasZeltyApiKey()) {
            return new JsonResponse([]);
        }

        $this->zeltyClient->setAuth($restaurant->getZeltyApiKey());
        $dishes = $this->zeltyClient->getDishes();

        usort($dishes, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return new JsonResponse(array_values(array_map(
            fn($d) => ['id' => $d['id'], 'name' => $d['name']],
            $dishes
        )));
    }

    #[Route('/admin/restaurant/{id}/zelty/delivery-dish', name: 'admin_restaurant_zelty_create_delivery_dish', methods: ['POST'])]
    public function createDeliveryDish(LocalBusiness $restaurant): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$restaurant->hasZeltyApiKey()) {
            return new JsonResponse(['error' => 'No Zelty API key configured'], 400);
        }

        $this->zeltyClient->setAuth($restaurant->getZeltyApiKey());

        $dish = $this->zeltyClient->createDish([
            'name'  => 'Frais de livraison',
            'price' => 0,
        ]);

        if (empty($dish['id'])) {
            return new JsonResponse(['error' => 'Zelty did not return a dish ID'], 500);
        }

        $restaurant->setZeltyDeliveryFeeDishId($dish['id']);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $dish['id'], 'name' => $dish['name'] ?? 'Frais de livraison']);
    }
}
