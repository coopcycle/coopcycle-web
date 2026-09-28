<?php

namespace AppBundle\Service\Shift;

use AppBundle\Entity\HolidayRequest;
use AppBundle\Entity\HolidayRequestRepository;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Payroll variables per employee over a date range (typically a month, but
 * any custom range is supported), for export to the coop's payroll process:
 * planned hours, actually worked hours (reported adjustments taken into
 * account, see ShiftTimeAdjustment), overtime (worked - planned) and
 * approved holiday days.
 */
final class PayrollExporter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param \DateTimeImmutable $start range start, inclusive
     * @param \DateTimeImmutable $end   range end, exclusive
     *
     * @return array<int, array{
     *     username: string,
     *     fullName: string,
     *     plannedHours: float,
     *     workedHours: float,
     *     overtimeHours: float,
     *     holidayDays: int
     * }> one row per employee with activity in the range, sorted by username
     */
    public function rows(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $start = $start->setTime(0, 0);
        $end = $end->setTime(0, 0);

        /** @var array<string, array{user: User, plannedHours: float, workedHours: float, overtimeHours: float, holidayDays: int}> $byUser */
        $byUser = [];

        $blank = ['plannedHours' => 0.0, 'workedHours' => 0.0, 'overtimeHours' => 0.0, 'holidayDays' => 0];

        $shifts = $this->entityManager->getRepository(Shift::class)->findOverlappingRange(
            \DateTime::createFromImmutable($start),
            \DateTime::createFromImmutable($end)
        );

        foreach ($shifts as $shift) {
            // A shift belongs to the range it starts in, so range totals
            // never double-count boundary shifts
            if ($shift->getStartsAt() < $start || $shift->getStartsAt() >= $end) {
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
            \DateTime::createFromImmutable($start),
            // endDate is inclusive, the range query compares dates
            \DateTime::createFromImmutable($end->modify('-1 day')),
            [HolidayRequest::STATUS_APPROVED]
        );

        foreach ($holidays as $holiday) {
            /** @var User $user */
            $user = $holiday->getUser();
            $username = $user->getUserIdentifier();
            $byUser[$username] ??= $blank + ['user' => $user];

            $byUser[$username]['holidayDays'] += self::daysWithinRange($holiday, $start, $end);
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
     * Number of holiday days (start & end inclusive) falling within the range.
     */
    private static function daysWithinRange(HolidayRequest $holiday, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $from = max(
            new \DateTimeImmutable($holiday->getStartDate()->format('Y-m-d')),
            $start
        );
        $to = min(
            new \DateTimeImmutable($holiday->getEndDate()->format('Y-m-d')),
            $end->modify('-1 day')
        );

        if ($to < $from) {
            return 0;
        }

        return $from->diff($to)->days + 1;
    }
}
