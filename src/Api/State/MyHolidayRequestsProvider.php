<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\HolidayRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class MyHolidayRequestsProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $filters = $context['filters'] ?? [];

        $qb = $this->entityManager
            ->getRepository(HolidayRequest::class)
            ->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $this->security->getUser())
            ->orderBy('o.createdAt', 'DESC');

        // Same overlap semantics as HolidayRequestDateFilter: a request counts
        // as being "in" the range as soon as it overlaps it, so a holiday
        // spanning several weeks shows up in each of them.
        //
        // The filter class itself never runs on this operation — API Platform
        // does not apply its Doctrine extensions to a custom provider — hence
        // the duplication here.
        //
        // Opt-in: with no date filter the whole history is returned, which is
        // what every app version released before the week picker expects.
        if (isset($filters['date']['after'])) {
            $qb->andWhere('o.endDate >= :after')
                ->setParameter('after', $filters['date']['after']);
        }

        if (isset($filters['date']['before'])) {
            $qb->andWhere('o.startDate <= :before')
                ->setParameter('before', $filters['date']['before']);
        }

        return $qb->getQuery()->getResult();
    }
}
