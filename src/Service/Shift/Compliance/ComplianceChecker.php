<?php

namespace AppBundle\Service\Shift\Compliance;

use AppBundle\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Checks a week of shift assignments against the configured legal constraints
 * (see LegalConfig / ConstraintTemplates) and reports violations per user.
 * Informational only: dispatchers get warnings, nothing is ever blocked.
 */
final class ComplianceChecker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegalConfig $legalConfig,
        private readonly RuleEvaluator $evaluator)
    {
    }

    /**
     * @return array{template: ?string, violations: array<int, array<string, mixed>>}
     */
    public function check(\DateTimeImmutable $weekStart): array
    {
        $weekStart = $weekStart->setTime(0, 0);

        $template = $this->legalConfig->getTemplate();
        $rules = $this->legalConfig->getEffectiveRules();

        if (null === $template || count($rules) === 0) {
            return ['template' => null, 'violations' => []];
        }

        // Load enough context around the checked week: the rolling average
        // looks back over its window, rest & consecutive-days rules look at
        // the neighboring days/weeks
        $windowWeeks = max((int) ($rules['avgWeeklyHoursWindowWeeks'] ?? 12), 2);
        $rangeStart = $weekStart->modify(sprintf('-%d days', ($windowWeeks - 1) * 7));
        $rangeEnd = $weekStart->modify('+14 days');

        $shifts = $this->entityManager->getRepository(Shift::class)
            ->findOverlappingRange(
                \DateTime::createFromImmutable($rangeStart),
                \DateTime::createFromImmutable($rangeEnd)
            );

        $byUser = [];
        foreach ($shifts as $shift) {
            $interval = [
                'start' => \DateTimeImmutable::createFromMutable($shift->getStartsAt()),
                'end' => \DateTimeImmutable::createFromMutable($shift->getEndsAt()),
                'breakMinutes' => $shift->getBreakMinutes(),
            ];
            foreach ($shift->getAssignedUsers() as $user) {
                $byUser[$user->getUserIdentifier()][] = $interval;
            }
        }

        ksort($byUser);

        $violations = [];
        foreach ($byUser as $username => $userShifts) {
            foreach ($this->evaluator->evaluate($userShifts, $rules, $weekStart) as $violation) {
                $violations[] = array_merge(['username' => $username], $violation);
            }
        }

        return ['template' => $template, 'violations' => $violations];
    }
}
