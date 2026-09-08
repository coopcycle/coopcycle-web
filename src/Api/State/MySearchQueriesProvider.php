<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\SearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class MySearchQueriesProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $filters = $context['filters'] ?? [];

        $qb = $this->entityManager
            ->getRepository(SearchQuery::class)
            ->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $this->security->getUser())
            ->orderBy('s.createdAt', 'DESC');

        // The filter class itself never runs on this operation - API
        // Platform does not apply its Doctrine extensions to a custom
        // provider - hence the duplication here (same as
        // MyHolidayRequestsProvider).
        if (isset($filters['scope'])) {
            $qb->andWhere('s.scope = :scope')
                ->setParameter('scope', $filters['scope']);
        }

        return $qb->getQuery()->getResult();
    }
}
