<?php

namespace AppBundle\Service\Shift;

use AppBundle\Entity\HolidayRequest;
use AppBundle\Entity\HolidayRequestRepository;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Monthly payroll variables per employee, for export to the coop's payroll
 * process: planned hours, actually worked hours (reported adjustments taken
 * into account, see ShiftTimeAdjustment), overtime (worked - planned) and
 * approved holiday days.
 */
final class PayrollExporter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param \DateTimeImmutable $monthStart any date in the target month
     *
     * @return array<int, array{
     *     username: string,
     *     fullName: string,
     *     plannedHours: float,
     *     workedHours: float,
     *     overtimeHours: float,
     *     holidayDays: int
     * }> one row per employee with activity that month, sorted by username
     */
    public function rows(\DateTimeImmutable $monthStart): array
    {
        $monthStart = $monthStart->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $monthStart->modify('+1 month');

        /** @var array<string, array{user: User, plannedHours: float, workedHours: float, overtimeHours: float, holidayDays: int}> $byUser */
        $byUser = [];

        $blank = ['plannedHours' => 0.0, 'workedHours' => 0.0, 'overtimeHours' => 0.0, 'holidayDays' => 0];

        $shifts = $this->entityManager->getRepository(Shift::class)->findOverlappingRange(
            \DateTime::createFromImmutable($monthStart),
            \DateTime::createFromImmutable($monthEnd)
        );

        foreach ($shifts as $shift) {
            // A shift belongs to the month it starts in, so month totals
            // never double-count boundary shifts
            if ($shift->getStartsAt() < $monthStart || $shift->getStartsAt() >= $monthEnd) {
                continue;
            }

            $planned = self::netHours($shift->getStartsAt(), $shift->getEndsAt(), $shift->getBreakMinutes());

            foreach ($shift->getAssignments() as $assignment) {
                /** @var User $user */
                $user = $assignment->getUser();
                $username = $user->getUserIdentifier();
                $byUser[$username] ??= $blank + ['user' => $user];

                $adjustment = $assignment->getAdjustment();
                $worked = null !== $adjustment
                    ? self::netHours($adjustment->getStartsAt(), $adjustment->getEndsAt(), $adjustment->getBreakMinutes())
                    : $planned;

                $byUser[$username]['plannedHours'] += $planned;
                $byUser[$username]['workedHours'] += $worked;
                $byUser[$username]['overtimeHours'] += $worked - $planned;
            }
        }

        /** @var HolidayRequestRepository $holidayRepository */
        $holidayRepository = $this->entityManager->getRepository(HolidayRequest::class);
        $holidays = $holidayRepository->findOverlappingRange(
            \DateTime::createFromImmutable($monthStart),
            // endDate is inclusive, the range query compares dates
            \DateTime::createFromImmutable($monthEnd->modify('-1 day')),
            [HolidayRequest::STATUS_APPROVED]
        );

        foreach ($holidays as $holiday) {
            /** @var User $user */
            $user = $holiday->getUser();
            $username = $user->getUserIdentifier();
            $byUser[$username] ??= $blank + ['user' => $user];

            $byUser[$username]['holidayDays'] += self::daysWithinMonth($holiday, $monthStart, $monthEnd);
        }

        ksort($byUser);

        $rows = [];
        foreach ($byUser as $username => $data) {
            $rows[] = [
                'username' => $username,
                'fullName' => trim(sprintf('%s %s', $data['user']->getGivenName() ?? '', $data['user']->getFamilyName() ?? '')),
                'plannedHours' => round($data['plannedHours'], 2),
                'workedHours' => round($data['workedHours'], 2),
                'overtimeHours' => round($data['overtimeHours'], 2),
                'holidayDays' => $data['holidayDays'],
            ];
        }

        return $rows;
    }

    private static function netHours(\DateTime $start, \DateTime $end, int $breakMinutes): float
    {
        $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600 - $breakMinutes / 60;

        return max(0.0, $hours);
    }

    /**
     * Number of holiday days (start & end inclusive) falling within the month.
     */
    private static function daysWithinMonth(HolidayRequest $holiday, \DateTimeImmutable $monthStart, \DateTimeImmutable $monthEnd): int
    {
        $from = max(
            new \DateTimeImmutable($holiday->getStartDate()->format('Y-m-d')),
            $monthStart
        );
        $to = min(
            new \DateTimeImmutable($holiday->getEndDate()->format('Y-m-d')),
            $monthEnd->modify('-1 day')
        );

        if ($to < $from) {
            return 0;
        }

        return $from->diff($to)->days + 1;
    }
}
