<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\SchedulePublication;
use AppBundle\Entity\SchedulePublicationRepository;
use AppBundle\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The shifts couriers can apply to: all shifts of the requested range whose
 * week has been published (draft weeks stay invisible to couriers).
 */
final class OpenShiftsProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $filters = $context['filters'] ?? [];

        $after = new \DateTime($filters['date']['after'] ?? 'monday this week');
        $after->setTime(0, 0);

        $before = isset($filters['date']['before']) ?
            new \DateTime($filters['date']['before']) : (clone $after)->modify('+6 days');
        $before->setTime(0, 0);
        $before->modify('+1 day');

        /** @var SchedulePublicationRepository $publicationRepository */
        $publicationRepository = $this->entityManager->getRepository(SchedulePublication::class);

        $publishedWeeks = $publicationRepository->findWeekStartsBetween(
            (clone $after)->modify('monday this week'),
            (clone $before)->modify('monday this week')
        );

        if (0 === count($publishedWeeks)) {
            return [];
        }

        $shifts = $this->entityManager
            ->getRepository(Shift::class)
            ->findOverlappingRange($after, $before);

        return array_values(array_filter($shifts, function (Shift $shift) use ($publishedWeeks) {
            $weekStart = (clone $shift->getStartsAt())->modify('monday this week');

            return in_array($weekStart->format('Y-m-d'), $publishedWeeks, true);
        }));
    }
}
