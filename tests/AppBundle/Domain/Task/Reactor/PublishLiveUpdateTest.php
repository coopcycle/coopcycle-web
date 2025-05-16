<?php

namespace Tests\AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskCancelled;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Domain\Task\Event\TaskFailed;
use AppBundle\Domain\Task\Event\TaskListUpdated;
use AppBundle\Domain\Task\Event\TaskListUpdatedv2;
use AppBundle\Domain\Task\Event\TaskRescheduled;
use AppBundle\Domain\Task\Event\TaskStarted;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Domain\Task\Reactor\PublishLiveUpdate;
use AppBundle\Domain\Tour\Event\TourCreated;
use AppBundle\Domain\Tour\Event\TourUpdated;
use AppBundle\Entity\TaskList;
use AppBundle\Service\LiveUpdates;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use AppBundle\Entity\User;

class PublishLiveUpdateTest extends TestCase
{
    use ProphecyTrait;

    private PublishLiveUpdate $publishLiveUpdate;
    private $liveUpdates;

    public function setUp(): void
    {
        $this->liveUpdates = $this->prophesize(LiveUpdates::class);
        $this->publishLiveUpdate = new PublishLiveUpdate(
            $this->liveUpdates->reveal()
        );
    }

    public function testTaskListUpdated()
    {
        $user = $this->prophesize(User::class);
        $event = $this->prophesize(TaskListUpdated::class);

        $event->getCourier()
            ->willReturn($user->reveal())
            ->shouldBeCalledOnce();

        $this->liveUpdates->toUsers(
            [$user->reveal()],
            $event->reveal()
        )->shouldBeCalledOnce();

        ($this->publishLiveUpdate)($event->reveal());
    }

    public function testTaskEventsForAdminsAndDispatchers()
    {
        $taskEvents = [
            TaskAssigned::class,
            TaskCancelled::class,
            TaskCreated::class,
            TaskDone::class,
            TaskFailed::class,
            TaskRescheduled::class,
            TaskStarted::class,
            TaskUnassigned::class,
            TaskUpdated::class,
        ];

        foreach ($taskEvents as $eventClass) {
            $event = $this->prophesize($eventClass);

            $this->liveUpdates->toRoles(
                ['ROLE_ADMIN', 'ROLE_DISPATCHER'],
                $event->reveal()
            )->shouldBeCalledOnce();

            ($this->publishLiveUpdate)($event->reveal());

            // Reset mock expectations for next iteration
            $this->liveUpdates = $this->prophesize(LiveUpdates::class);
            $this->publishLiveUpdate = new PublishLiveUpdate(
                $this->liveUpdates->reveal()
            );
        }
    }

    public function testTaskListUpdatedv2()
    {
        $user = $this->prophesize(User::class);
        $event = $this->prophesize(TaskListUpdatedv2::class);
        $taskList = $this->prophesize(TaskList::class);

        $taskList->getCourier()
            ->willReturn($user->reveal())
            ->shouldBeCalledOnce();

        $event->getTaskList()
            ->willReturn($taskList->reveal())
            ->shouldBeCalledOnce();

        $this->liveUpdates->toUserAndRoles(
            $user->reveal(),
            ['ROLE_ADMIN', 'ROLE_DISPATCHER'],
            $event->reveal()
        )->shouldBeCalledOnce();

        ($this->publishLiveUpdate)($event->reveal());
    }

    public function testTourEvents()
    {
        $tourEvents = [
            TourCreated::class,
            TourUpdated::class,
        ];

        foreach ($tourEvents as $eventClass) {
            $event = $this->prophesize($eventClass);

            $this->liveUpdates->toRoles(
                ['ROLE_ADMIN', 'ROLE_DISPATCHER'],
                $event->reveal()
            )->shouldBeCalledOnce();

            ($this->publishLiveUpdate)($event->reveal());

            // Reset mock expectations for next iteration
            $this->liveUpdates = $this->prophesize(LiveUpdates::class);
            $this->publishLiveUpdate = new PublishLiveUpdate(
                $this->liveUpdates->reveal()
            );
        }
    }

    public function testDefaultCase()
    {
        $event = $this->prophesize(Event::class);

        $this->liveUpdates->toRoles(
            ['ROLE_ADMIN', 'ROLE_DISPATCHER'],
            $event->reveal()
        )->shouldBeCalledOnce();

        ($this->publishLiveUpdate)($event->reveal());
    }
}
