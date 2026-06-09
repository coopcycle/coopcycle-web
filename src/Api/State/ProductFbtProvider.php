<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\ProductFbtDto;
use AppBundle\Entity\Sylius\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ProductFbtProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        #[Autowire(service: 'recommender.client')] private readonly HttpClientInterface $recommenderClient,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly SerializerInterface $serializer,
        private readonly IriConverterInterface $iriConverter,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var Product $product */
        $product = $this->provider->provide($operation, $uriVariables, $context);

        $request = $this->requestStack->getCurrentRequest();
        $restaurantIri = $request?->query->get('restaurant');
        $n = max(1, min(20, (int) ($request?->query->get('n', 5) ?? 5)));

        $dto = new ProductFbtDto();

        try {
            $queryParams = [
                'product' => $this->iriConverter->getIriFromResource($product),
                'n'       => $n,
            ];

            if ($restaurantIri !== null) {
                $queryParams['restaurant'] = $restaurantIri;
            }

            $iris = $this->recommenderClient
                ->request('GET', '/recommendations/frequently-bought-together', ['query' => $queryParams])
                ->toArray()['recommendations'] ?? [];

            if (empty($iris)) {
                return $dto;
            }

            $ids = array_values(array_filter(
                array_map(fn(string $iri) => (int) basename($iri), $iris)
            ));

            $products = $this->entityManager->getRepository(Product::class)
                ->findBy(['id' => $ids, 'enabled' => true]);

            // Preserve recommender ranking order
            $idOrder = array_flip($ids);
            usort($products, fn($a, $b) =>
                ($idOrder[$a->getId()] ?? PHP_INT_MAX) <=> ($idOrder[$b->getId()] ?? PHP_INT_MAX)
            );

            foreach ($products as $p) {
                $restaurantId = $p->getRestaurant()?->getId();
                if (!$restaurantId) {
                    continue;
                }

                $dto->items[] = [
                    'product'    => $this->serializer->normalize($p, 'jsonld', ['groups' => ['product']]),
                    'options'    => array_values(
                        $this->serializer->normalize($p->getOptions()->toArray(), null, ['groups' => ['restaurant_menu']]) ?? []
                    ),
                    'formAction' => $this->router->generate('restaurant_add_product_to_cart', [
                        'id'   => $restaurantId,
                        'code' => $p->getCode(),
                    ]),
                ];
            }
        } catch (TransportExceptionInterface|\Throwable) {
            // Graceful degradation — recommender unavailable or product has no data
        }

        return $dto;
    }
}
