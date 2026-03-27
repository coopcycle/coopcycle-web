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
use AppBundle\Message\Task\PublishLiveUpdate as PublishLiveUpdateMessage;
use AppBundle\MessageHandler\Task\PublishLiveUpdateHandler;
use AppBundle\Service\LiveUpdates;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class PublishLiveUpdateTest extends TestCase
{
    use ProphecyTrait;

    private $liveUpdates;
    private $entityManager;
    private $taskRepository;
    private PublishLiveUpdateHandler $handler;

    public function setUp(): void
    {
        $this->liveUpdates = $this->prophesize(LiveUpdates::class);
        $this->taskRepository = $this->prophesize(EntityRepository::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->entityManager
            ->getRepository(Task::class)
            ->willReturn($this->taskRepository->reveal());

        $this->handler = new PublishLiveUpdateHandler(
            $this->liveUpdates->reveal(),
            $this->entityManager->reveal(),
        );
    }

    public function testTaskNotFoundReturnsEarly(): void
    {
        $this->taskRepository->find(99)->willReturn(null);

        $this->liveUpdates->toRoles(Argument::any(), Argument::any())->shouldNotBeCalled();
        $this->liveUpdates->toUserAndRoles(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        ($this->handler)(new PublishLiveUpdateMessage(99, TaskDone::class));
    }

    /**
     * @dataProvider nonUpdatedTaskEventClassProvider
     */
    public function testNonUpdatedTaskEventsPublishToRoles(string $eventClass): void
    {
        $task = $this->prophesize(Task::class);
        $this->taskRepository->find(1)->willReturn($task->reveal());

        $this->liveUpdates
            ->toRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER'], Argument::type($eventClass))
            ->shouldBeCalledOnce();

        ($this->handler)(new PublishLiveUpdateMessage(1, $eventClass));
    }

    public function nonUpdatedTaskEventClassProvider(): array
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

    public function testTaskUpdatedWithCourierPublishesToUserAndRoles(): void
    {
        $courier = $this->prophesize(User::class);
        $task = $this->prophesize(Task::class);
        $task->getAssignedCourier()->willReturn($courier->reveal());

        $this->taskRepository->find(1)->willReturn($task->reveal());

        $this->liveUpdates
            ->toUserAndRoles(
                $courier->reveal(),
                ['ROLE_ADMIN', 'ROLE_DISPATCHER'],
                Argument::type(TaskUpdated::class)
            )
            ->shouldBeCalledOnce();

        $this->liveUpdates->toRoles(Argument::any(), Argument::any())->shouldNotBeCalled();

        ($this->handler)(new PublishLiveUpdateMessage(1, TaskUpdated::class));
    }

    public function testTaskUpdatedWithoutCourierPublishesToRoles(): void
    {
        $task = $this->prophesize(Task::class);
        $task->getAssignedCourier()->willReturn(null);

        $this->taskRepository->find(1)->willReturn($task->reveal());

        $this->liveUpdates
            ->toRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER'], Argument::type(TaskUpdated::class))
            ->shouldBeCalledOnce();

        $this->liveUpdates->toUserAndRoles(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        ($this->handler)(new PublishLiveUpdateMessage(1, TaskUpdated::class));
    }
}
