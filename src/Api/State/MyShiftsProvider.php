<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class MyShiftsProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        $filters = $context['filters'] ?? [];

        $after = new \DateTime($filters['date']['after'] ?? 'monday this week');
        $after->setTime(0, 0);

        $before = isset($filters['date']['before']) ?
            new \DateTime($filters['date']['before']) : (clone $after)->modify('+6 days');
        $before->setTime(0, 0);
        $before->modify('+1 day');

        return $this->entityManager
            ->getRepository(Shift::class)
            ->findForUserBetween($user, $after, $before);
    }
}
