<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Returns the values (stores, restaurants, authors, customers) used to
 * populate the filter dropdowns of the admin incidents table.
 */
final class IncidentFiltersProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache
    )
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $suggestions = $this->cache->get('incident_filters_suggestion', function(ItemInterface $item) {
            $item->expiresAfter(5 * 60);
            /** @var IncidentRepository $repo */
            $repo = $this->entityManager->getRepository(Incident::class);
            return $repo->getFiltersSuggestions();
        });

        return new JsonResponse($suggestions);
    }
}
