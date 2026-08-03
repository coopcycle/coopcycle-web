<?php

namespace AppBundle\Service;

use AppBundle\Entity\HolidayRequestRepository;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftAssignment;
use AppBundle\Entity\TaskList;
use AppBundle\Message\PushNotification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShiftManager
{
    private array $ensuredTaskLists = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private TranslatorInterface $translator,
        private LoggerInterface $logger)
    {
    }

    /**
     * Synchronizes the assignments of a shift with the given list of users.
     *
     * @param UserInterface[] $users
     * @return UserInterface[] the newly assigned users
     */
    public function syncAssignments(Shift $shift, array $users): array
    {
        $added = [];

        foreach ($shift->getAssignments() as $assignment) {
            if (!in_array($assignment->getUser(), $users, true)) {
                $shift->removeAssignment($assignment);
            }
        }

        foreach ($users as $user) {
            if (!$shift->isAssigned($user)) {
                $assignment = new ShiftAssignment();
                $assignment->setUser($user);
                $shift->addAssignment($assignment);
                $added[] = $user;
            }
        }

        return $added;
    }

    /**
     * Makes sure a courier assigned to a shift shows up in the dispatch,
     * by creating an empty TaskList for the day of the shift.
     *
     * Users without the ROLE_COURIER role are skipped. This is a manual,
     * dispatcher-triggered action (see addWeekToDispatch()) — shifts do NOT
     * do this automatically on create/update/copy, since a shift assignment
     * doesn't always mean the dispatcher wants that day's dispatch touched yet.
     *
     * @return bool true if a TaskList was created, false if one already
     *              existed or the user isn't a courier
     */
    public function addToDispatch(UserInterface $user, \DateTimeInterface $date): bool
    {
        if (!in_array('ROLE_COURIER', $user->getRoles(), true)) {
            return false;
        }

        $day = new \DateTime($date->format('Y-m-d'));
        $key = sprintf('%s|%s', $user->getUserIdentifier(), $day->format('Y-m-d'));

        // TaskLists persisted in this request are not visible
        // to findOneBy() until the next flush
        if (isset($this->ensuredTaskLists[$key])) {
            return false;
        }
        $this->ensuredTaskLists[$key] = true;

        $taskList = $this->entityManager
            ->getRepository(TaskList::class)
            ->findOneBy(['courier' => $user, 'date' => $day]);

        if (null !== $taskList) {
            return false;
        }

        $taskList = new TaskList();
        $taskList->setCourier($user);
        $taskList->setDate($day);
        $this->entityManager->persist($taskList);

        return true;
    }

    /**
     * Adds every courier assigned to a shift in the given week to the
     * dispatch, by creating an empty TaskList for the day of each of their
     * shifts. Triggered manually by a dispatcher from the planning UI.
     *
     * @return UserInterface[] couriers newly added to the dispatch
     */
    public function addWeekToDispatch(\DateTimeImmutable $weekStart): array
    {
        $start = $weekStart->setTime(0, 0);
        $end = $start->modify('+7 days');

        $shifts = $this->entityManager->getRepository(Shift::class)->findOverlappingRange($start, $end);

        $added = [];
        foreach ($shifts as $shift) {
            foreach ($shift->getAssignedUsers() as $user) {
                if ($this->addToDispatch($user, $shift->getStartsAt())) {
                    $added[$user->getUsername()] = $user;
                }
            }
        }

        $this->entityManager->flush();

        return array_values($added);
    }

    /**
     * Copies all the shifts of the source week to the target week, including assignments,
     * skipping users having an approved holiday on the target day.
     *
     * @return Shift[] the newly created shifts
     */
    public function copyWeek(\DateTimeImmutable $sourceMonday, \DateTimeImmutable $targetMonday): array
    {
        $shiftRepository = $this->entityManager->getRepository(Shift::class);
        /** @var HolidayRequestRepository $holidayRequestRepository */
        $holidayRequestRepository = $this->entityManager->getRepository(\AppBundle\Entity\HolidayRequest::class);

        $sourceStart = $sourceMonday->setTime(0, 0);
        $sourceEnd = $sourceStart->modify('+7 days');

        $interval = $sourceMonday->setTime(0, 0)->diff($targetMonday->setTime(0, 0));

        $copies = [];
        $assignedUsers = [];

        foreach ($shiftRepository->findOverlappingRange($sourceStart, $sourceEnd) as $shift) {

            $copy = new Shift();
            $copy->setType($shift->getType());
            $copy->setSlots($shift->getSlots());
            $copy->setStartsAt((clone $shift->getStartsAt())->add($interval));
            $copy->setEndsAt((clone $shift->getEndsAt())->add($interval));
            $copy->setCreatedBy($shift->getCreatedBy());

            foreach ($shift->getAssignedUsers() as $user) {

                if ($holidayRequestRepository->hasApprovedHolidayOnDate($user, $copy->getStartsAt())
                ||  $holidayRequestRepository->hasApprovedHolidayOnDate($user, $copy->getEndsAt())) {
                    continue;
                }

                $assignment = new ShiftAssignment();
                $assignment->setUser($user);
                $copy->addAssignment($assignment);

                $assignedUsers[$user->getUsername()] = $user;
            }

            $this->entityManager->persist($copy);
            $copies[] = $copy;
        }

        $this->entityManager->flush();

        if (count($assignedUsers) > 0) {
            $this->notify(
                $this->translator->trans('notifications.shifts.week_assigned', [
                    '%date%' => $targetMonday->format('Y-m-d'),
                ]),
                array_values($assignedUsers)
            );
        }

        return $copies;
    }

    /**
     * @param UserInterface[] $users
     */
    public function notify(string $message, array $users, array $data = []): void
    {
        if (count($users) === 0) {
            return;
        }

        if (!RemotePushNotificationManager::isEnabled()) {
            return;
        }

        try {
            $this->messageBus->dispatch(
                new PushNotification($message, "", $users, $data)
            );
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Could not send shift push notification: %s', $e->getMessage()));
        }
    }
}
