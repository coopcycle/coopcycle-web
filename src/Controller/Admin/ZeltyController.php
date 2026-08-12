<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Integration\Zelty\ZeltyClient;
use AppBundle\Integration\Zelty\ZeltyConnectService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class ZeltyController extends AbstractController
{
    public function __construct(
        private readonly ZeltyClient $zeltyClient,
        private readonly ZeltyConnectService $zeltyConnectService,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/admin/restaurant/{id}/zelty/connect', name: 'admin_restaurant_zelty_connect', methods: ['POST'])]
    public function connect(LocalBusiness $restaurant, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $apiKey = is_array($data) ? trim((string) ($data['apiKey'] ?? '')) : '';

        // "•" is the placeholder shown for an already saved key — reject it as-is.
        if ($apiKey === '' || str_contains($apiKey, '•') || str_contains($apiKey, '*')) {
            return new JsonResponse(['error' => 'missing_or_masked_key'], 400);
        }

        try {
            $secretSaved = $this->zeltyConnectService->connect($restaurant, $apiKey);
        } catch (ClientExceptionInterface $e) {
            return new JsonResponse(['error' => 'invalid_key'], 422);
        } catch (ExceptionInterface $e) {
            return new JsonResponse(['error' => 'zelty_unreachable'], 502);
        }

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'connected', 'secretSaved' => $secretSaved]);
    }

    #[Route('/admin/restaurant/{id}/zelty/webhook-secret', name: 'admin_restaurant_zelty_webhook_secret', methods: ['POST'])]
    public function webhookSecret(LocalBusiness $restaurant, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $secretKey = is_array($data) ? trim((string) ($data['secretKey'] ?? '')) : '';

        if ($secretKey === '' || str_contains($secretKey, '*') || str_contains($secretKey, '•')) {
            return new JsonResponse(['error' => 'missing_or_masked_secret'], 400);
        }

        $restaurant->setZeltyWebhookSecretKey($secretKey);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'saved']);
    }

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
