<?php

namespace Tests\AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskCancelled;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Domain\Task\Event\TaskFailed;
use AppBundle\Domain\Task\Event\TaskRescheduled;
use AppBundle\Domain\Task\Event\TaskStarted;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\MessageHandler\Task\PublishLiveUpdate;
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

    public function testTaskUpdatedWithCourier()
    {
        $user = $this->prophesize(User::class);
        $event = $this->prophesize(TaskUpdated::class);
        
        $event->getCourier()->willReturn($user->reveal());
        
        $this->liveUpdates->toUserAndRoles(
            $user->reveal(),
            ['ROLE_ADMIN', 'ROLE_DISPATCHER'],
            $event->reveal()
        )->shouldBeCalledOnce();

        ($this->publishLiveUpdate)($event->reveal());
    }

    public function testTaskUpdatedWithoutCourier()
    {
        $event = $this->prophesize(TaskUpdated::class);
        
        $event->getCourier()->willReturn(null);
        
        $this->liveUpdates->toRoles(
            ['ROLE_ADMIN', 'ROLE_DISPATCHER'],
            $event->reveal()
        )->shouldBeCalledOnce();

        ($this->publishLiveUpdate)($event->reveal());
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
