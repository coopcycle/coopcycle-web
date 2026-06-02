<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\CustomerRecommendationsDto;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
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
        $type = $request?->query->get('type', 'product') ?? 'product';
        $n = (int) ($request?->query->get('n', 5) ?? 5);

        $customerIri = $this->iriConverter->getIriFromResource($customer);

        $dto = new CustomerRecommendationsDto();

        try {
            $response = $this->recommenderClient->request('GET', '/recommendations', [
                'query' => [
                    'customer' => $customerIri,
                    'type'     => $type,
                    'n'        => max(1, min(20, $n)),
                ],
            ]);
            $data = $response->toArray();
            $iris = $data['recommendations'] ?? [];

            if ($iris !== []) {
                $entityClass = $type === 'restaurant' ? LocalBusiness::class : Product::class;
                $ids = array_filter(array_map(fn(string $iri) => (int) basename($iri), $iris));
                $entities = $this->entityManager->getRepository($entityClass)
                    ->findBy(['id' => $ids, 'enabled' => true]);
                $dto->recommendations = array_map(
                    fn($entity) => $this->iriConverter->getIriFromResource($entity),
                    $entities
                );
            }
        } catch (TransportExceptionInterface|\Throwable) {
            // Graceful degradation — recommender unavailable
        }

        return $dto;
    }
}
