<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\HolidayRequest;
use AppBundle\Entity\HolidayRequestRepository;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftAssignment;
use AppBundle\Entity\ShiftRepository;
use AppBundle\Entity\ShiftTemplate;
use AppBundle\Entity\ShiftTemplateShift;
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
        $shift->setActivity('delivery');
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
        $this->assertEquals('delivery', $copy->getActivity());
        $this->assertEquals(2, $copy->getSlots());
        $this->assertEquals(new \DateTime('2026-06-30 09:00:00'), $copy->getStartsAt());
        $this->assertEquals(new \DateTime('2026-06-30 17:00:00'), $copy->getEndsAt());
        $this->assertSame([$alice, $bob], $copy->getAssignedUsers());
    }

    public function testCopyWeekSupportsMultiWeekGap()
    {
        $shift = new Shift();
        $shift->setActivity('dispatch');
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

    public function testClearWeekDeletesEveryShiftOverlappingTheWeek()
    {
        $shift1 = new Shift();
        $shift1->setActivity('delivery');
        $shift1->setStartsAt(new \DateTime('2026-06-23 09:00:00'));
        $shift1->setEndsAt(new \DateTime('2026-06-23 17:00:00'));

        $shift2 = new Shift();
        $shift2->setActivity('dispatch');
        $shift2->setStartsAt(new \DateTime('2026-06-25 08:00:00'));
        $shift2->setEndsAt(new \DateTime('2026-06-25 13:00:00'));

        $this->shiftRepository
            ->findOverlappingRange(Argument::type(\DateTimeInterface::class), Argument::type(\DateTimeInterface::class))
            ->willReturn([$shift1, $shift2]);

        $removed = [];
        $this->entityManager
            ->remove(Argument::type(Shift::class))
            ->will(function ($args) use (&$removed) {
                $removed[] = $args[0];
            });
        $this->entityManager->flush()->shouldBeCalled();

        $cleared = $this->shiftManager->clearWeek(new \DateTimeImmutable('2026-06-22'));

        $this->assertSame(2, $cleared);
        $this->assertSame([$shift1, $shift2], $removed);
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

        $created = $this->shiftManager->addToDispatch($sarah, new \DateTime('2026-06-29 09:00:00'));

        $this->assertTrue($created);
        $this->assertCount(1, $persisted);
        $this->assertSame($sarah, $persisted[0]->getCourier());
        $this->assertEquals(new \DateTime('2026-06-29 00:00:00'), $persisted[0]->getDate());

        // Idempotent within the same request
        $again = $this->shiftManager->addToDispatch($sarah, new \DateTime('2026-06-29 15:00:00'));
        $this->assertFalse($again);
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

        $created = $this->shiftManager->addToDispatch($sarah, new \DateTime('2026-06-29 09:00:00'));

        $this->assertFalse($created);
    }

    public function testAddToDispatchSkipsNonCouriers()
    {
        $bob = $this->createUser('bob');
        $bob->addRole('ROLE_DISPATCHER');

        $this->taskListRepository->findOneBy(Argument::any())->shouldNotBeCalled();
        $this->entityManager->persist(Argument::any())->shouldNotBeCalled();

        $created = $this->shiftManager->addToDispatch($bob, new \DateTime('2026-06-29 09:00:00'));

        $this->assertFalse($created);
    }

    public function testCopyWeekDoesNotTouchDispatch()
    {
        $sarah = $this->createUser('sarah');
        $sarah->addRole('ROLE_COURIER');

        $shift = new Shift();
        $shift->setActivity('delivery');
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

        // copyWeek must not read or write TaskLists at all — dispatch sync is
        // now a separate, manual, dispatcher-triggered action
        $this->taskListRepository->findOneBy(Argument::any())->shouldNotBeCalled();
        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->shouldNotBeCalled();
        $this->entityManager->persist(Argument::type(Shift::class))->willReturn(null);
        $this->entityManager->flush()->shouldBeCalled();

        $this->shiftManager->copyWeek(
            new \DateTimeImmutable('2026-06-22'),
            new \DateTimeImmutable('2026-06-29')
        );
    }

    public function testAddWeekToDispatchAddsAssignedCouriers()
    {
        $sarah = $this->createUser('sarah');
        $sarah->addRole('ROLE_COURIER');
        $bob = $this->createUser('bob');
        $bob->addRole('ROLE_DISPATCHER');

        $shift = new Shift();
        $shift->setActivity('delivery');
        $shift->setStartsAt(new \DateTime('2026-06-29 09:00:00'));
        $shift->setEndsAt(new \DateTime('2026-06-29 17:00:00'));

        foreach ([$sarah, $bob] as $user) {
            $assignment = new ShiftAssignment();
            $assignment->setUser($user);
            $shift->addAssignment($assignment);
        }

        $this->shiftRepository
            ->findOverlappingRange(Argument::cetera())
            ->willReturn([$shift]);
        $this->taskListRepository
            ->findOneBy(Argument::any())
            ->willReturn(null);

        $persisted = [];
        $this->entityManager
            ->persist(Argument::type(TaskList::class))
            ->will(function ($args) use (&$persisted) {
                $persisted[] = $args[0];
            });
        $this->entityManager->flush()->shouldBeCalled();

        $added = $this->shiftManager->addWeekToDispatch(new \DateTimeImmutable('2026-06-29'));

        // Only sarah is a courier; bob (dispatcher) is skipped
        $this->assertSame([$sarah], $added);
        $this->assertCount(1, $persisted);
        $this->assertSame($sarah, $persisted[0]->getCourier());
    }

    public function testAddWeekToDispatchIsIdempotent()
    {
        $sarah = $this->createUser('sarah');
        $sarah->addRole('ROLE_COURIER');

        $shift = new Shift();
        $shift->setActivity('delivery');
        $shift->setStartsAt(new \DateTime('2026-06-29 09:00:00'));
        $shift->setEndsAt(new \DateTime('2026-06-29 17:00:00'));

        $assignment = new ShiftAssignment();
        $assignment->setUser($sarah);
        $shift->addAssignment($assignment);

        $this->shiftRepository
            ->findOverlappingRange(Argument::cetera())
            ->willReturn([$shift]);
        // TaskList already exists for sarah on that day
        $this->taskListRepository
            ->findOneBy(Argument::any())
            ->willReturn(new TaskList());

        $this->entityManager->persist(Argument::type(TaskList::class))->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalled();

        $added = $this->shiftManager->addWeekToDispatch(new \DateTimeImmutable('2026-06-29'));

        $this->assertSame([], $added);
    }

    public function testCopyWeekSkipsUsersOnApprovedHoliday()
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');

        $shift = new Shift();
        $shift->setActivity('delivery');
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

    public function testCreateTemplateFromWeekSnapshotsShapeAndAssignees()
    {
        $alice = $this->createUser('alice');

        $shift = new Shift();
        $shift->setActivity('delivery');
        $shift->setSlots(2);
        $shift->setBreakMinutes(30);
        $shift->setComment('note');
        $shift->setStartsAt(new \DateTime('2026-06-24 09:00:00')); // a Wednesday
        $shift->setEndsAt(new \DateTime('2026-06-24 17:00:00'));

        $assignment = new ShiftAssignment();
        $assignment->setUser($alice);
        $shift->addAssignment($assignment);

        $this->shiftRepository
            ->findOverlappingRange(Argument::cetera())
            ->willReturn([$shift]);

        $this->entityManager->persist(Argument::type(ShiftTemplate::class))->willReturn(null);
        $this->entityManager->flush()->shouldBeCalled();

        $template = $this->shiftManager->createTemplateFromWeek(
            'My template',
            new \DateTimeImmutable('2026-06-22'),
            $this->createUser('dispatcher')
        );

        $this->assertSame('My template', $template->getName());
        $this->assertCount(1, $template->getShifts());

        $line = $template->getShifts()->first();
        $this->assertSame('delivery', $line->getActivity());
        $this->assertSame(3, $line->getDayOfWeek()); // Wednesday = ISO 3
        $this->assertSame('09:00', $line->getStartTime()->format('H:i'));
        $this->assertSame('17:00', $line->getEndTime()->format('H:i'));
        $this->assertSame(2, $line->getSlots());
        $this->assertSame(30, $line->getBreakMinutes());
        $this->assertSame('note', $line->getComment());
        $this->assertSame([$alice], $line->getAssignedUsers()->toArray());
    }

    private function makeTemplateLine(int $dayOfWeek, string $start, string $end, int $slots = 1, int $breakMinutes = 0): ShiftTemplateShift
    {
        $line = new ShiftTemplateShift();
        $line->setActivity('delivery');
        $line->setDayOfWeek($dayOfWeek);
        $line->setStartTime(new \DateTime($start));
        $line->setEndTime(new \DateTime($end));
        $line->setSlots($slots);
        $line->setBreakMinutes($breakMinutes);

        return $line;
    }

    public function testApplyTemplateCreatesShiftsOnTheRightDayAndTime()
    {
        $template = new ShiftTemplate();
        $template->addShift($this->makeTemplateLine(1, '09:00', '17:00', 2, 30)); // Monday
        $template->addShift($this->makeTemplateLine(4, '12:00', '15:00'));        // Thursday

        $this->entityManager->persist(Argument::type(Shift::class))->willReturn(null);
        $this->entityManager->flush()->shouldBeCalled();

        $created = $this->shiftManager->applyTemplate(
            $template,
            new \DateTimeImmutable('2026-07-06'), // a Monday
            false
        );

        $this->assertCount(2, $created);

        $this->assertSame('2026-07-06 09:00:00', $created[0]->getStartsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-06 17:00:00', $created[0]->getEndsAt()->format('Y-m-d H:i:s'));
        $this->assertSame(2, $created[0]->getSlots());
        $this->assertSame(30, $created[0]->getBreakMinutes());

        $this->assertSame('2026-07-09 12:00:00', $created[1]->getStartsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-09 15:00:00', $created[1]->getEndsAt()->format('Y-m-d H:i:s'));
    }

    public function testApplyTemplateWithoutIncludeAssigneesCreatesNoAssignments()
    {
        $alice = $this->createUser('alice');

        $line = $this->makeTemplateLine(1, '09:00', '17:00');
        $line->addAssignedUser($alice);

        $template = new ShiftTemplate();
        $template->addShift($line);

        $this->entityManager->persist(Argument::type(Shift::class))->willReturn(null);
        $this->entityManager->flush()->shouldBeCalled();

        $created = $this->shiftManager->applyTemplate(
            $template,
            new \DateTimeImmutable('2026-07-06'),
            false
        );

        $this->assertSame([], $created[0]->getAssignedUsers());
    }

    public function testApplyTemplateWithIncludeAssigneesCopiesAssignmentsAndSkipsHolidayUsers()
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');

        $line = $this->makeTemplateLine(1, '09:00', '17:00');
        $line->addAssignedUser($alice);
        $line->addAssignedUser($bob);

        $template = new ShiftTemplate();
        $template->addShift($line);

        $this->holidayRequestRepository
            ->hasApprovedHolidayOnDate($bob, Argument::any())
            ->willReturn(true);
        $this->holidayRequestRepository
            ->hasApprovedHolidayOnDate($alice, Argument::any())
            ->willReturn(false);

        $this->entityManager->persist(Argument::type(Shift::class))->willReturn(null);
        $this->entityManager->flush()->shouldBeCalled();

        $created = $this->shiftManager->applyTemplate(
            $template,
            new \DateTimeImmutable('2026-07-06'),
            true
        );

        $this->assertSame([$alice], $created[0]->getAssignedUsers());
    }

    public function testApplyTemplateCopiesRequiredSkills()
    {
        $skill = new \AppBundle\Entity\Skill();

        $line = $this->makeTemplateLine(1, '09:00', '17:00');
        $line->addRequiredSkill($skill);

        $template = new ShiftTemplate();
        $template->addShift($line);

        $this->entityManager->persist(Argument::type(Shift::class))->willReturn(null);
        $this->entityManager->flush()->shouldBeCalled();

        $created = $this->shiftManager->applyTemplate(
            $template,
            new \DateTimeImmutable('2026-07-06'),
            false
        );

        $this->assertSame([$skill], $created[0]->getRequiredSkills()->toArray());
    }
}
