<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Zelty\ApiLog;
use AppBundle\Integration\Zelty\ZeltyClient;
use AppBundle\Integration\Zelty\ZeltyConnectService;
use AppBundle\Sylius\Taxation\TaxesHelper;
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
        private readonly TaxesHelper $taxesHelper,
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
            $this->zeltyConnectService->connect($restaurant, $apiKey);
        } catch (ClientExceptionInterface $e) {
            return new JsonResponse(['error' => 'invalid_key'], 422);
        } catch (ExceptionInterface $e) {
            return new JsonResponse(['error' => 'zelty_unreachable'], 502);
        }

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'connected']);
    }

    #[Route('/admin/restaurant/{id}/zelty/logs', name: 'admin_restaurant_zelty_logs', methods: ['GET'])]
    public function logs(LocalBusiness $restaurant, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $limit = min(max($request->query->getInt('limit', 25), 1), 100);
        $offset = max($request->query->getInt('offset'), 0);

        $qb = $this->entityManager->getRepository(ApiLog::class)->createQueryBuilder('l');
        $qb
            // Brand-wide webhooks (dish.update…) carry no restaurant: Zelty does not
            // tell us which shop they belong to, so they are shown everywhere.
            ->andWhere($qb->expr()->orX('l.restaurant = :restaurant', 'l.restaurant IS NULL'))
            ->setParameter('restaurant', $restaurant)
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit + 1)
            ->setFirstResult($offset);

        $logs = $qb->getQuery()->getResult();

        $hasMore = count($logs) > $limit;
        $logs = array_slice($logs, 0, $limit);

        return new JsonResponse([
            'hasMore' => $hasMore,
            'logs'    => array_map(fn(ApiLog $log) => [
                'id'           => $log->getId(),
                'createdAt'    => $log->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'direction'    => $log->getDirection(),
                'method'       => $log->getMethod(),
                'endpoint'     => $log->getEndpoint(),
                'statusCode'   => $log->getStatusCode(),
                'durationMs'   => $log->getDurationMs(),
                'requestBody'  => $log->getRequestBody(),
                'responseBody' => $log->getResponseBody(),
                'error'        => $log->getError(),
                'successful'   => $log->isSuccessful(),
                'events'       => $log->getEvents(),
            ], $logs),
        ]);
    }

    #[Route('/admin/restaurant/{id}/zelty/dishes', name: 'admin_restaurant_zelty_dishes', methods: ['GET'])]
    public function dishes(LocalBusiness $restaurant): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$restaurant->hasZeltyApiKey()) {
            return new JsonResponse([]);
        }

        $this->zeltyClient->setRestaurant($restaurant);
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

        $this->zeltyClient->setRestaurant($restaurant);

        // Zelty expects VAT rates as an integer, where 1000 = 10%.
        $serviceTaxRate = $this->taxesHelper->getServiceTaxRate();
        $tax = $serviceTaxRate !== null ? (int) round($serviceTaxRate->getAmount() * 10000) : 0;

        try {
            $dish = $this->zeltyClient->createDish([
                'name'         => 'Frais de livraison',
                'price'        => 0,
                'tax'          => $tax,
                'tax_takeaway' => $tax,
                'tax_delivery' => $tax,
            ]);
        } catch (ExceptionInterface $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        }

        if (empty($dish['id'])) {
            return new JsonResponse(['error' => 'Zelty did not return a dish ID'], 500);
        }

        $restaurant->setZeltyDeliveryFeeDishId($dish['id']);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $dish['id'], 'name' => $dish['name'] ?? 'Frais de livraison']);
    }
}
