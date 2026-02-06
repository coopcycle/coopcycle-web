<?php

namespace AppBundle\Action\Incident;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class IncidentFastList
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache
    )
    {}

    public function __invoke($data, Request $request): JsonResponse
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
