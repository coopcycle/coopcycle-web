<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\CustomerRecommendationsDto;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CustomerRecommendationsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly HttpClientInterface $recommenderClient,
        private readonly RequestStack $requestStack,
        private readonly IriConverterInterface $iriConverter,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $customer = $this->provider->provide($operation, $uriVariables, $context);

        $request = $this->requestStack->getCurrentRequest();
        $type        = $request?->query->get('type', 'product') ?? 'product';
        $n           = max(1, min(20, (int) ($request?->query->get('n', 5) ?? 5)));
        $restaurantIri = $request?->query->get('restaurant');
        $excludeIris   = $request?->query->all('exclude') ?? [];

        $customerIri = $this->iriConverter->getIriFromResource($customer);

        $dto = new CustomerRecommendationsDto();

        $hasFilters = $restaurantIri !== null || $excludeIris !== [];

        try {
            $response = $this->recommenderClient->request('GET', '/recommendations', [
                'query' => [
                    'customer' => $customerIri,
                    'type'     => $type,
                    // Fetch more when filters are active to account for attrition
                    'n'        => $hasFilters ? 20 : $n,
                ],
            ]);
            $data = $response->toArray();
            $iris = $data['recommendations'] ?? [];

            if ($iris !== []) {
                $entityClass = $type === 'restaurant' ? LocalBusiness::class : Product::class;
                $ids = array_filter(array_map(fn(string $iri) => (int) basename($iri), $iris));

                // Exclude products whose variants are already in the cart
                if ($excludeIris !== [] && $entityClass === Product::class) {
                    $variantIds = array_filter(
                        array_map(fn(string $iri) => (int) basename($iri), $excludeIris)
                    );
                    if ($variantIds !== []) {
                        $excludedProductIds = $this->entityManager->createQueryBuilder()
                            ->select('p.id')
                            ->from(ProductVariant::class, 'pv')
                            ->join('pv.product', 'p')
                            ->where('pv.id IN (:ids)')
                            ->setParameter('ids', $variantIds)
                            ->getQuery()
                            ->getSingleColumnResult();
                        $ids = array_diff($ids, $excludedProductIds);
                    }
                }

                $criteria = ['id' => array_values($ids), 'enabled' => true];

                // Scope to the restaurant being ordered from
                if ($restaurantIri !== null && $entityClass === Product::class) {
                    $criteria['restaurant'] = (int) basename($restaurantIri);
                }

                $entities = $this->entityManager->getRepository($entityClass)
                    ->findBy($criteria);

                $recommendations = array_map(
                    fn($entity) => $this->iriConverter->getIriFromResource($entity),
                    $entities
                );

                $dto->recommendations = array_slice($recommendations, 0, $n);
            }
        } catch (TransportExceptionInterface|\Throwable) {
            // Graceful degradation — recommender unavailable
        }

        return $dto;
    }
}
