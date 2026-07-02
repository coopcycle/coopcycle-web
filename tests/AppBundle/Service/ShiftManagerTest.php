<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\HolidayRequest;
use AppBundle\Entity\HolidayRequestRepository;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftAssignment;
use AppBundle\Entity\ShiftRepository;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskListRepository;
use AppBundle\Entity\User;
use AppBundle\Service\ShiftManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShiftManagerTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $messageBus;
    private $translator;
    private $shiftManager;
    private $shiftRepository;
    private $holidayRequestRepository;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->logger = $this->prophesize(LoggerInterface::class);

        $this->shiftRepository = $this->prophesize(ShiftRepository::class);
        $this->holidayRequestRepository = $this->prophesize(HolidayRequestRepository::class);
        $this->taskListRepository = $this->prophesize(TaskListRepository::class);

        $this->entityManager
            ->getRepository(Shift::class)
            ->willReturn($this->shiftRepository->reveal());
        $this->entityManager
            ->getRepository(HolidayRequest::class)
            ->willReturn($this->holidayRequestRepository->reveal());
        $this->entityManager
            ->getRepository(TaskList::class)
            ->willReturn($this->taskListRepository->reveal());

        $this->translator
            ->trans(Argument::type('string'), Argument::any())
            ->willReturn('Notification');

        $this->messageBus
            ->dispatch(Argument::any())
            ->will(fn ($args) => new Envelope($args[0]));

        $this->shiftManager = new ShiftManager(
            $this->entityManager->reveal(),
            $this->messageBus->reveal(),
            $this->translator->reveal(),
            $this->logger->reveal()
        );
    }

    private function createUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);

        return $user;
    }

    public function testSyncAssignmentsAddsAndRemovesUsers()
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $charlie = $this->createUser('charlie');

        $shift = new Shift();

        $aliceAssignment = new ShiftAssignment();
        $aliceAssignment->setUser($alice);
        $shift->addAssignment($aliceAssignment);

        $bobAssignment = new ShiftAssignment();
        $bobAssignment->setUser($bob);
        $shift->addAssignment($bobAssignment);

        $added = $this->shiftManager->syncAssignments($shift, [$alice, $charlie]);

        $this->assertSame([$charlie], $added);
        $this->assertSame([$alice, $charlie], $shift->getAssignedUsers());
    }

    public function testSyncAssignmentsWithNoChanges()
    {
        $alice = $this->createUser('alice');

        $shift = new Shift();

        $assignment = new ShiftAssignment();
        $assignment->setUser($alice);
        $shift->addAssignment($assignment);

        $added = $this->shiftManager->syncAssignments($shift, [$alice]);

        $this->assertSame([], $added);
        $this->assertSame([$alice], $shift->getAssignedUsers());
    }

    public function testCopyWeekShiftsDatesAndCopiesAssignments()
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');

        $shift = new Shift();
        $shift->setType('drive');
        $shift->setSlots(2);
        $shift->setStartsAt(new \DateTime('2026-06-23 09:00:00'));
        $shift->setEndsAt(new \DateTime('2026-06-23 17:00:00'));

        foreach ([$alice, $bob] as $user) {
            $assignment = new ShiftAssignment();
            $assignment->setUser($user);
            $shift->addAssignment($assignment);
        }

        $this->shiftRepository
            ->findOverlappingRange(Argument::type(\DateTimeInterface::class), Argument::type(\DateTimeInterface::class))
            ->willReturn([$shift]);

        $this->holidayRequestRepository
            ->hasApprovedHolidayOnDate(Argument::any(), Argument::any())
            ->willReturn(false);

        $persisted = [];
        $this->entityManager
            ->persist(Argument::type(Shift::class))
            ->will(function ($args) use (&$persisted) {
                $persisted[] = $args[0];
            });
        $this->entityManager->flush()->shouldBeCalled();

        $copies = $this->shiftManager->copyWeek(
            new \DateTimeImmutable('2026-06-22'),
            new \DateTimeImmutable('2026-06-29')
        );

        $this->assertCount(1, $copies);
        $this->assertCount(1, $persisted);

        $copy = $copies[0];
        $this->assertEquals('drive', $copy->getType());
        $this->assertEquals(2, $copy->getSlots());
        $this->assertEquals(new \DateTime('2026-06-30 09:00:00'), $copy->getStartsAt());
        $this->assertEquals(new \DateTime('2026-06-30 17:00:00'), $copy->getEndsAt());
        $this->assertSame([$alice, $bob], $copy->getAssignedUsers());
    }

    public function testCopyWeekSupportsMultiWeekGap()
    {
        $shift = new Shift();
        $shift->setType('dispatch');
        $shift->setStartsAt(new \DateTime('2026-06-22 08:00:00'));
        $shift->setEndsAt(new \DateTime('2026-06-22 13:00:00'));

        $this->shiftRepository
            ->findOverlappingRange(Argument::cetera())
            ->willReturn([$shift]);

        $this->entityManager->persist(Argument::type(Shift::class))->willReturn(null);
        $this->entityManager->flush()->shouldBeCalled();

        $copies = $this->shiftManager->copyWeek(
            new \DateTimeImmutable('2026-06-22'),
            new \DateTimeImmutable('2026-07-13')
        );

        $this->assertEquals(new \DateTime('2026-07-13 08:00:00'), $copies[0]->getStartsAt());
        $this->assertEquals(new \DateTime('2026-07-13 13:00:00'), $copies[0]->getEndsAt());
    }

    public function testAddToDispatchCreatesTaskListForCourier()
    {
        $sarah = $this->createUser('sarah');
        $sarah->addRole('ROLE_COURIER');

        $this->taskListRepository
            ->findOneBy(Argument::any())
            ->willReturn(null);

        $persisted = [];
        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->will(function ($args) use (&$persisted) {
                $persisted[] = $args[0];
            });

        $this->shiftManager->addToDispatch($sarah, new \DateTime('2026-06-29 09:00:00'));

        $this->assertCount(1, $persisted);
        $this->assertSame($sarah, $persisted[0]->getCourier());
        $this->assertEquals(new \DateTime('2026-06-29 00:00:00'), $persisted[0]->getDate());

        // Idempotent within the same request
        $this->shiftManager->addToDispatch($sarah, new \DateTime('2026-06-29 15:00:00'));
        $this->assertCount(1, $persisted);
    }

    public function testAddToDispatchDoesNothingWhenTaskListExists()
    {
        $sarah = $this->createUser('sarah');
        $sarah->addRole('ROLE_COURIER');

        $this->taskListRepository
            ->findOneBy(Argument::any())
            ->willReturn(new TaskList());

        $this->entityManager->persist(Argument::any())->shouldNotBeCalled();

        $this->shiftManager->addToDispatch($sarah, new \DateTime('2026-06-29 09:00:00'));
    }

    public function testAddToDispatchSkipsNonCouriers()
    {
        $bob = $this->createUser('bob');
        $bob->addRole('ROLE_DISPATCHER');

        $this->taskListRepository->findOneBy(Argument::any())->shouldNotBeCalled();
        $this->entityManager->persist(Argument::any())->shouldNotBeCalled();

        $this->shiftManager->addToDispatch($bob, new \DateTime('2026-06-29 09:00:00'));
    }

    public function testCopyWeekAddsCouriersToDispatch()
    {
        $sarah = $this->createUser('sarah');
        $sarah->addRole('ROLE_COURIER');

        $shift = new Shift();
        $shift->setType('drive');
        $shift->setStartsAt(new \DateTime('2026-06-23 09:00:00'));
        $shift->setEndsAt(new \DateTime('2026-06-23 17:00:00'));

        $assignment = new ShiftAssignment();
        $assignment->setUser($sarah);
        $shift->addAssignment($assignment);

        $this->shiftRepository
            ->findOverlappingRange(Argument::cetera())
            ->willReturn([$shift]);
        $this->holidayRequestRepository
            ->hasApprovedHolidayOnDate(Argument::cetera())
            ->willReturn(false);
        $this->taskListRepository
            ->findOneBy(Argument::any())
            ->willReturn(null);

        $persistedTaskLists = [];
        $this->entityManager
            ->persist(Argument::any())
            ->will(function ($args) use (&$persistedTaskLists) {
                if ($args[0] instanceof TaskList) {
                    $persistedTaskLists[] = $args[0];
                }
            });
        $this->entityManager->flush()->shouldBeCalled();

        $this->shiftManager->copyWeek(
            new \DateTimeImmutable('2026-06-22'),
            new \DateTimeImmutable('2026-06-29')
        );

        $this->assertCount(1, $persistedTaskLists);
        $this->assertEquals(new \DateTime('2026-06-30 00:00:00'), $persistedTaskLists[0]->getDate());
    }

    public function testCopyWeekSkipsUsersOnApprovedHoliday()
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');

        $shift = new Shift();
        $shift->setType('drive');
        $shift->setStartsAt(new \DateTime('2026-06-23 09:00:00'));
        $shift->setEndsAt(new \DateTime('2026-06-23 17:00:00'));

        foreach ([$alice, $bob] as $user) {
            $assignment = new ShiftAssignment();
            $assignment->setUser($user);
            $shift->addAssignment($assignment);
        }

        $this->shiftRepository
            ->findOverlappingRange(Argument::cetera())
            ->willReturn([$shift]);

        // Bob has an approved holiday on the target day
        $this->holidayRequestRepository
            ->hasApprovedHolidayOnDate($bob, Argument::any())
            ->willReturn(true);
        $this->holidayRequestRepository
            ->hasApprovedHolidayOnDate($alice, Argument::any())
            ->willReturn(false);

        $this->entityManager->persist(Argument::type(Shift::class))->willReturn(null);
        $this->entityManager->flush()->shouldBeCalled();

        $copies = $this->shiftManager->copyWeek(
            new \DateTimeImmutable('2026-06-22'),
            new \DateTimeImmutable('2026-06-29')
        );

        $this->assertSame([$alice], $copies[0]->getAssignedUsers());
    }
}
