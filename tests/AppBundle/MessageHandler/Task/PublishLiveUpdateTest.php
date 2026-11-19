<?php

namespace Tests\AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskCancelled;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Domain\Task\Event\TaskFailed;
use AppBundle\Domain\Task\Event\TaskRescheduled;
use AppBundle\Domain\Task\Event\TaskStarted;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\MessageHandler\Task\PublishLiveUpdate;
use AppBundle\Service\LiveUpdates;
use AppBundle\Service\LiveUpdatesSuspension;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class PublishLiveUpdateTest extends TestCase
{
    use ProphecyTrait;

    private LiveUpdatesSuspension $suspension;

    private PublishLiveUpdate $handler;
    private $liveUpdates;

    public function setUp(): void
    {
        $this->liveUpdates = $this->prophesize(LiveUpdates::class);
        $this->suspension = new LiveUpdatesSuspension();
        $this->handler = new PublishLiveUpdate($this->liveUpdates->reveal(), $this->suspension);
    }

    /**
     * A delivery import sent one per task, with the notifications that go with it,
     * a dozen calls to Centrifugo per line: it sends a single event instead
     */
    public function testNothingIsSentWhileSuspended(): void
    {
        $event = $this->prophesize(TaskCreated::class);

        $this->suspension->during(fn () => ($this->handler)($event->reveal()));

        $this->liveUpdates->toDispatchers(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->liveUpdates->toUserAndDispatchers(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function taskEventClassProvider(): array
    {
        return [
            [TaskAssigned::class],
            [TaskCancelled::class],
            [TaskCreated::class],
            [TaskDone::class],
            [TaskFailed::class],
            [TaskRescheduled::class],
            [TaskStarted::class],
            [TaskUnassigned::class],
        ];
    }

    /**
     * The handler receives the event, which already holds the task -- there is no
     * second read of the entity, so the payload cannot describe an older state
     * than the one the event is about.
     *
     * @dataProvider taskEventClassProvider
     */
    public function testTaskEventsGoToDispatchers(string $eventClass): void
    {
        $event = $this->prophesize($eventClass);

        $this->liveUpdates->toDispatchers($event->reveal())->shouldBeCalledOnce();
        $this->liveUpdates->toUserAndDispatchers(Argument::cetera())->shouldNotBeCalled();

        ($this->handler)($event->reveal());
    }

    /**
     * A task update also reaches the courier it is assigned to, so the rider app
     * sees changes to its own task without holding a dispatcher role.
     */
    public function testTaskUpdatedAlsoGoesToTheAssignedCourier(): void
    {
        $courier = $this->prophesize(User::class);

        $task = $this->prophesize(Task::class);
        $task->getAssignedCourier()->willReturn($courier->reveal());

        $event = $this->prophesize(TaskUpdated::class);
        $event->getTask()->willReturn($task->reveal());

        $this->liveUpdates
            ->toUserAndDispatchers($courier->reveal(), $event->reveal())
            ->shouldBeCalledOnce();
        $this->liveUpdates->toDispatchers(Argument::cetera())->shouldNotBeCalled();

        ($this->handler)($event->reveal());
    }

    public function testTaskUpdatedWithNoCourierGoesToDispatchersOnly(): void
    {
        $task = $this->prophesize(Task::class);
        $task->getAssignedCourier()->willReturn(null);

        $event = $this->prophesize(TaskUpdated::class);
        $event->getTask()->willReturn($task->reveal());

        $this->liveUpdates->toDispatchers($event->reveal())->shouldBeCalledOnce();
        $this->liveUpdates->toUserAndDispatchers(Argument::cetera())->shouldNotBeCalled();

        ($this->handler)($event->reveal());
    }
}
