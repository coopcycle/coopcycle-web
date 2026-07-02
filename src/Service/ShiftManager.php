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
     * Users without the ROLE_COURIER role are skipped.
     */
    public function addToDispatch(UserInterface $user, \DateTimeInterface $date): void
    {
        if (!in_array('ROLE_COURIER', $user->getRoles(), true)) {
            return;
        }

        $day = new \DateTime($date->format('Y-m-d'));
        $key = sprintf('%s|%s', $user->getUserIdentifier(), $day->format('Y-m-d'));

        // TaskLists persisted in this request are not visible
        // to findOneBy() until the next flush
        if (isset($this->ensuredTaskLists[$key])) {
            return;
        }
        $this->ensuredTaskLists[$key] = true;

        $taskList = $this->entityManager
            ->getRepository(TaskList::class)
            ->findOneBy(['courier' => $user, 'date' => $day]);

        if (null === $taskList) {
            $taskList = new TaskList();
            $taskList->setCourier($user);
            $taskList->setDate($day);
            $this->entityManager->persist($taskList);
        }
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

                $this->addToDispatch($user, $copy->getStartsAt());

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
