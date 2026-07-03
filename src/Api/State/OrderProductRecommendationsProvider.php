<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\CustomerRecommendationsDto;
use AppBundle\Service\RecommenderProductService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class OrderProductRecommendationsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly RecommenderProductService $recommender,
        private readonly IriConverterInterface $iriConverter,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $order = $this->provider->provide($operation, $uriVariables, $context);

        if (!$this->authorizationChecker->isGranted('view', $order)) {
            throw new AccessDeniedException();
        }

        $dto = new CustomerRecommendationsDto();
        $dto->recommendations = array_map(
            fn($product) => $this->iriConverter->getIriFromResource($product),
            $this->recommender->getProductsForOrder($order)
        );

        return $dto;
    }
}
