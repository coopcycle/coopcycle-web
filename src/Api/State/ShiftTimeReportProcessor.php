<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftTimeReportInput;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftAssignment;
use AppBundle\Entity\ShiftTimeAdjustment;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Records the hours actually worked on a shift assignment (see
 * ShiftTimeAdjustment). Employees report for themselves, dispatchers for any
 * assignee. The planned shift itself is never touched.
 */
final class ShiftTimeReportProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security)
    {}

    /**
     * @param ShiftTimeReportInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Shift
    {
        $shift = $this->entityManager->getRepository(Shift::class)->find($uriVariables['id'] ?? 0);
        if (null === $shift) {
            throw new NotFoundHttpException('Shift not found');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $targetUser = $this->resolveTargetUser($data, $currentUser);

        $assignment = $this->findAssignment($shift, $targetUser);
        if (null === $assignment) {
            throw new BadRequestHttpException(sprintf('User "%s" is not assigned to this shift', $targetUser->getUserIdentifier()));
        }

        if ($data->clear) {
            if (null !== ($adjustment = $assignment->getAdjustment())) {
                $assignment->setAdjustment(null);
                $this->entityManager->remove($adjustment);
            }
            $this->entityManager->flush();

            return $shift;
        }

        if (null === $data->startsAt || null === $data->endsAt) {
            throw new BadRequestHttpException('startsAt and endsAt are required');
        }

        $startsAt = new \DateTime($data->startsAt);
        $endsAt = new \DateTime($data->endsAt);

        if ($endsAt <= $startsAt) {
            throw new BadRequestHttpException('endsAt must be after startsAt');
        }

        $adjustment = $assignment->getAdjustment() ?? new ShiftTimeAdjustment();
        $adjustment->setStartsAt($startsAt);
        $adjustment->setEndsAt($endsAt);
        $adjustment->setBreakMinutes($data->breakMinutes);
        $adjustment->setComment($data->comment);
        $adjustment->setReportedBy($currentUser);

        $assignment->setAdjustment($adjustment);
        $this->entityManager->persist($adjustment);
        $this->entityManager->flush();

        return $shift;
    }

    private function resolveTargetUser(ShiftTimeReportInput $data, User $currentUser): User
    {
        if (null === $data->user) {
            return $currentUser;
        }

        // Accept the same IRI shape used everywhere else (/api/users/{id})
        if (1 !== preg_match('#/users/(\d+)$#', $data->user, $matches)) {
            throw new BadRequestHttpException('Invalid user IRI');
        }

        $targetUser = $this->entityManager->getRepository(User::class)->find((int) $matches[1]);
        if (null === $targetUser) {
            throw new BadRequestHttpException('User not found');
        }

        if ($targetUser !== $currentUser && !$this->security->isGranted('ROLE_DISPATCHER')) {
            throw new AccessDeniedHttpException('Only dispatchers can report time for someone else');
        }

        return $targetUser;
    }

    private function findAssignment(Shift $shift, User $user): ?ShiftAssignment
    {
        foreach ($shift->getAssignments() as $assignment) {
            if ($assignment->getUser() === $user) {
                return $assignment;
            }
        }

        return null;
    }
}
