<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\ShiftDashboard;
use AppBundle\Entity\SchedulePublication;
use AppBundle\Entity\SchedulePublicationRepository;
use AppBundle\Entity\ShiftRepository;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ShiftDashboardProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ShiftDashboard
    {
        $request = $this->requestStack->getCurrentRequest();
        $weeksParam = $request?->query->get('weeks');
        $weeks = null !== $weeksParam ? max(1, (int) $weeksParam) : 5;

        $from = \DateTimeImmutable::createFromInterface(Carbon::now()->startOfWeek());

        /** @var ShiftRepository $shiftRepository */
        $shiftRepository = $this->entityManager->getRepository(\AppBundle\Entity\Shift::class);
        $rows = $shiftRepository->getWeeklyFillRates($from, $weeks);

        $byWeekStart = [];
        foreach ($rows as $row) {
            $byWeekStart[$row['week_start']] = $row;
        }

        /** @var SchedulePublicationRepository $publicationRepository */
        $publicationRepository = $this->entityManager->getRepository(SchedulePublication::class);
        $publishedWeeks = $publicationRepository->findWeekStartsBetween(
            $from,
            $from->modify(sprintf('+%d weeks', $weeks))
        );

        $result = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $from->modify(sprintf('+%d weeks', $i));
            $weekEnd = $weekStart->modify('+6 days');
            $key = $weekStart->format('Y-m-d');

            $row = $byWeekStart[$key] ?? null;
            $totalSlots = $row ? (int) $row['total_slots'] : 0;
            $totalAssignments = $row ? (int) $row['total_assignments'] : 0;
            $fillRate = $totalSlots > 0 ? $totalAssignments / $totalSlots : 0.0;

            $result[] = [
                'weekStart' => $weekStart->format('Y-m-d'),
                'weekEnd' => $weekEnd->format('Y-m-d'),
                'totalSlots' => $totalSlots,
                'totalAssignments' => $totalAssignments,
                'fillRate' => $fillRate,
                'published' => in_array($key, $publishedWeeks, true),
            ];
        }

        return new ShiftDashboard($result);
    }
}
