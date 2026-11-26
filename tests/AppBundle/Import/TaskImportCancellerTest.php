<?php

declare(strict_types=1);

namespace Tests\AppBundle\Import;

use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Task;
use AppBundle\Import\TaskImportCanceller;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Which of the tasks an import created are cancelled, and which are left as they are.
 */
class TaskImportCancellerTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $taskManager;
    private TaskImportCanceller $canceller;
    private ImportQueue $queue;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->taskManager = $this->prophesize(TaskManager::class);

        $this->canceller = new TaskImportCanceller(
            $this->entityManager->reveal(),
            $this->taskManager->reveal(),
            new NullLogger()
        );

        $this->queue = new ImportQueue(ImportQueue::TYPE_TASKS);
        (new \ReflectionProperty(ImportQueue::class, 'id'))->setValue($this->queue, 42);
    }

    private function givenTask(int $id, string $status): Task
    {
        $task = new Task();
        $task->setStatus($status);

        (new \ReflectionProperty(Task::class, 'id'))->setValue($task, $id);

        $this->entityManager->find(Task::class, $id)->willReturn($task);

        return $task;
    }

    public function testATaskStillToDoIsCancelled(): void
    {
        $task = $this->givenTask(1, Task::STATUS_TODO);

        $this->assertTrue($this->canceller->cancel(1, $this->queue));

        $this->taskManager->cancelTasks([$task])->shouldHaveBeenCalled();
    }

    /**
     * As from the dispatch board: being on a courier's list is no reason to keep it
     */
    public function testAnAssignedTaskIsCancelled(): void
    {
        $task = $this->givenTask(1, Task::STATUS_TODO);
        $task->assignTo(new \AppBundle\Entity\User(), new \DateTime());

        $this->assertTrue($this->canceller->cancel(1, $this->queue));

        $this->taskManager->cancelTasks([$task])->shouldHaveBeenCalled();
    }

    /**
     * So that a retried message, or a second dispatcher asking, counts them the same way
     */
    public function testAnAlreadyCancelledTaskCountsAsCancelled(): void
    {
        $this->givenTask(1, Task::STATUS_CANCELLED);

        $this->assertTrue($this->canceller->cancel(1, $this->queue));

        $this->taskManager->cancelTasks(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * @dataProvider underWayProvider
     */
    public function testATaskUnderWayIsLeftAsItIs(string $status): void
    {
        $this->givenTask(1, $status);

        $this->assertFalse($this->canceller->cancel(1, $this->queue));

        $this->taskManager->cancelTasks(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function underWayProvider(): array
    {
        return [
            [Task::STATUS_DOING],
            [Task::STATUS_DONE],
            [Task::STATUS_FAILED],
        ];
    }

    public function testATaskThatCouldNotBeCancelledIsLeftOutOfTheNextFlush(): void
    {
        $task = $this->givenTask(1, Task::STATUS_TODO);

        $this->taskManager->cancelTasks([$task])->willThrow(
            new HandlerFailedException(new Envelope(new \stdClass()), [new \RuntimeException('Nope')])
        );

        $this->assertFalse($this->canceller->cancel(1, $this->queue));

        $this->entityManager->refresh($task)->shouldHaveBeenCalled();
    }

    public function testATaskThatIsGoneIsSkipped(): void
    {
        $this->entityManager->find(Task::class, 404)->willReturn(null);

        $this->assertFalse($this->canceller->cancel(404, $this->queue));
    }
}
