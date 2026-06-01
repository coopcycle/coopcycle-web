<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\CustomerRecommendationsDto;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CustomerRecommendationsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly HttpClientInterface $recommenderClient,
        private readonly RequestStack $requestStack,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $this->provider->provide($operation, $uriVariables, $context);

        $request = $this->requestStack->getCurrentRequest();
        $type = $request?->query->get('type', 'product') ?? 'product';
        $n = (int) ($request?->query->get('n', 5) ?? 5);

        $customerId = $uriVariables['id'];
        $customerIri = "/api/customers/{$customerId}";

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
            $dto->recommendations = $data['recommendations'] ?? [];
        } catch (TransportExceptionInterface|\Throwable) {
            // Graceful degradation — recommender unavailable
        }

        return $dto;
    }
}
