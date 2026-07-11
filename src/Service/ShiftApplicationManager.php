<?php

namespace AppBundle\Service;

use AppBundle\Entity\SchedulePublication;
use AppBundle\Entity\SchedulePublicationRepository;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftAssignment;
use AppBundle\Entity\ShiftWaitlistEntry;
use AppBundle\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Couriers applying to shifts of a published week, first come first served:
 * a free slot means an assignment, a full shift means joining the waitlist,
 * and when an assignee unapplies the oldest waitlisted user is promoted.
 *
 * Unlike dispatcher-side staffing (which only warns), applying is blocked
 * when the user lacks a required skill or the week isn't published.
 */
class ShiftApplicationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShiftManager $shiftManager,
        private readonly TranslatorInterface $translator)
    {}

    public function apply(Shift $shift, User $user): void
    {
        $this->assertPublished($shift);
        $this->assertHasRequiredSkills($shift, $user);

        if ($shift->isAssigned($user) || $shift->isWaitlisted($user)) {
            throw new BadRequestHttpException('Already applied to this shift');
        }

        // Lock the shift row so two simultaneous applications can't both
        // grab the last slot (first come first served)
        $this->entityManager->wrapInTransaction(function () use ($shift, $user) {
            $this->entityManager->lock($shift, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($shift);

            if ($shift->getAssignments()->count() < $shift->getSlots()) {
                $assignment = new ShiftAssignment();
                $assignment->setUser($user);
                $shift->addAssignment($assignment);
            } else {
                $entry = new ShiftWaitlistEntry();
                $entry->setUser($user);
                $shift->addWaitlistEntry($entry);
            }

            $this->entityManager->flush();
        });
    }

    public function unapply(Shift $shift, User $user): void
    {
        $promoted = null;

        $this->entityManager->wrapInTransaction(function () use ($shift, $user, &$promoted) {
            $this->entityManager->lock($shift, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($shift);

            foreach ($shift->getWaitlist() as $entry) {
                if ($entry->getUser() === $user) {
                    $shift->removeWaitlistEntry($entry);
                    $this->entityManager->flush();

                    return;
                }
            }

            $assignment = null;
            foreach ($shift->getAssignments() as $a) {
                if ($a->getUser() === $user) {
                    $assignment = $a;
                    break;
                }
            }

            if (null === $assignment) {
                throw new BadRequestHttpException('Not applied to this shift');
            }

            $shift->removeAssignment($assignment);

            // Promote the first user in the queue to the freed slot
            $first = $shift->getWaitlist()->first();
            if (false !== $first) {
                $shift->removeWaitlistEntry($first);

                $promotedAssignment = new ShiftAssignment();
                $promotedAssignment->setUser($first->getUser());
                $shift->addAssignment($promotedAssignment);

                $promoted = $first->getUser();
            }

            $this->entityManager->flush();
        });

        // Reload collections so they serialize with sequential keys
        // (removeElement leaves holes, which would serialize as an object)
        $this->entityManager->refresh($shift);

        if (null !== $promoted) {
            $this->shiftManager->notify(
                $this->translator->trans('notifications.shifts.waitlist_promoted', [
                    '%date%' => $shift->getStartsAt()->format('Y-m-d'),
                ]),
                [$promoted]
            );
        }
    }

    private function assertPublished(Shift $shift): void
    {
        $weekStart = (clone $shift->getStartsAt())->modify('monday this week');

        /** @var SchedulePublicationRepository $repository */
        $repository = $this->entityManager->getRepository(SchedulePublication::class);

        if (null === $repository->findOneByWeekStart($weekStart)) {
            throw new BadRequestHttpException('The schedule for this week is not published yet');
        }
    }

    private function assertHasRequiredSkills(Shift $shift, User $user): void
    {
        $userSkillIds = [];
        foreach ($user->getSkills() as $skill) {
            $userSkillIds[] = $skill->getId();
        }

        $missing = [];
        foreach ($shift->getRequiredSkills() as $skill) {
            if (!in_array($skill->getId(), $userSkillIds, true)) {
                $missing[] = $skill->getName();
            }
        }

        if (count($missing) > 0) {
            throw new BadRequestHttpException(
                sprintf('Missing required skill(s): %s', implode(', ', $missing))
            );
        }
    }
}
