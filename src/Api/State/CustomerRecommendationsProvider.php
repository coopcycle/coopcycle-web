<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\CustomerRecommendationsDto;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire('%database_name%')] private readonly string $databaseName,
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
                    'instance' => $this->databaseName,
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
