<?php

declare(strict_types=1);

namespace Tests\AppBundle\Import;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\User;
use AppBundle\Import\TaskImportHandler;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * What a row of a task file becomes, inside the transaction the worker opens.
 */
class TaskImportHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $validator;
    private $taskListProvider;
    private TaskImportHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->entityManager->persist(Argument::any())->willReturn(null);
        $this->entityManager->flush()->willReturn(null);

        $this->validator = $this->prophesize(ValidatorInterface::class);
        $this->validator->validate(Argument::any())->willReturn(new ConstraintViolationList());

        $this->taskListProvider = $this->prophesize(TaskListProvider::class);

        $this->handler = new TaskImportHandler(
            $this->entityManager->reveal(),
            new Filesystem(new InMemoryFilesystemAdapter()),
            $this->prophesize(TaskSpreadsheetParser::class)->reveal(),
            $this->validator->reveal(),
            $this->taskListProvider->reveal()
        );
    }

    private function createQueue(int $id = 42, ?Organization $organization = null): ImportQueue
    {
        $queue = new ImportQueue(ImportQueue::TYPE_TASKS);
        $queue->setFilename('tasks.csv');
        $queue->setOrganization($organization);

        (new \ReflectionProperty(ImportQueue::class, 'id'))->setValue($queue, $id);

        return $queue;
    }

    private function createTask(string $after = '2026-11-25 10:00', string $before = '2026-11-25 12:00'): Task
    {
        $task = new Task();
        $task->setAfter(new \DateTime($after));
        $task->setBefore(new \DateTime($before));

        return $task;
    }

    private function row(Task $task, ?string $groupName = null, ?array $assign = null): array
    {
        return ['task' => $task, 'groupName' => $groupName, 'assign' => $assign];
    }

    private function save(ImportQueue $queue, SpreadsheetParseResult $result): array
    {
        $rowNumber = null;

        return $this->handler->save($queue, $result, $rowNumber);
    }

    public function testTheTasksOfTheFileAreSaved(): void
    {
        $queue = $this->createQueue();

        $first = $this->createTask('2026-11-25 10:00', '2026-11-25 12:00');
        $second = $this->createTask('2026-11-26 10:00', '2026-11-26 12:00');

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($first));
        $result->addData(3, $this->row($second));

        $saved = $this->save($queue, $result);

        $this->entityManager->persist(Argument::type(Task::class))->shouldHaveBeenCalledTimes(2);

        $this->assertSame([$first, $second], $saved);

        // The days whose boards have to reload
        $this->assertSame(['2026-11-25', '2026-11-26'], $this->handler->datesOf($saved));
    }

    /**
     * How a dispatcher finds, and cancels, what a file brought in
     */
    public function testEveryTaskIsTaggedWithTheImport(): void
    {
        $queue = $this->createQueue(7);

        $task = $this->createTask();

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($task));

        $this->save($queue, $result);

        $this->assertSame(7, $task->getMetadata()['task_import_id']);
    }

    public function testRowsAreGroupedByTheGroupTheyName(): void
    {
        $queue = $this->createQueue();

        $first = $this->createTask();
        $second = $this->createTask();
        $third = $this->createTask();

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($first, 'Monday round'));
        $result->addData(3, $this->row($second, 'Monday round'));
        $result->addData(4, $this->row($third, 'Tuesday round'));

        $this->save($queue, $result);

        $this->assertSame('Monday round', $first->getGroup()->getName());
        $this->assertSame($first->getGroup(), $second->getGroup());
        $this->assertSame('Tuesday round', $third->getGroup()->getName());
    }

    public function testRowsWithNoGroupShareTheImportsOwnGroup(): void
    {
        $queue = $this->createQueue();

        $first = $this->createTask();
        $second = $this->createTask();
        $named = $this->createTask();

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($first));
        $result->addData(3, $this->row($named, 'Monday round'));
        $result->addData(4, $this->row($second));

        $this->save($queue, $result);

        $this->assertInstanceOf(TaskGroup::class, $first->getGroup());
        $this->assertSame($first->getGroup(), $second->getGroup());
        $this->assertStringStartsWith('Import ', $first->getGroup()->getName());
        $this->assertNotSame($first->getGroup(), $named->getGroup());
    }

    /**
     * Appending a task to a courier's list writes to a list that is already saved, so it
     * happens here rather than while the file is read: a rollback undoes it
     */
    public function testAnAssignedRowIsPutInTheCouriersList(): void
    {
        $queue = $this->createQueue();

        $courier = new User();
        $date = new \DateTime('2026-11-26');

        $taskList = new TaskList();
        $taskList->setDate($date);
        $taskList->setCourier($courier);

        $this->taskListProvider->getTaskListForUserAndDate($date, $courier)->willReturn($taskList);

        $task = $this->createTask();

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($task, null, [$courier, $date]));

        $this->save($queue, $result);

        $this->assertTrue($task->isAssignedTo($courier));
    }

    public function testTheOrganizationOfTheImportIsSetOnTheTasks(): void
    {
        $organization = new Organization();

        $queue = $this->createQueue(42, $organization);

        $task = $this->createTask();

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($task));

        $this->save($queue, $result);

        $this->assertSame($organization, $task->getOrganization());
    }

    /**
     * A row the validator refuses is reported on its row and left out, as on the delivery
     * side: the rest of the file is still imported
     */
    public function testARowThatFailsValidationIsReportedAndSkipped(): void
    {
        $queue = $this->createQueue();

        $good = $this->createTask();
        // A different day, so the two are told apart: Prophecy compares objects loosely,
        // and two tasks built the same way are equal to each other
        $bad = $this->createTask('2026-12-01 10:00', '2026-12-01 12:00');

        $this->validator->validate($bad)->willReturn(new ConstraintViolationList([
            new ConstraintViolation('should not be blank', null, [], $bad, 'address', null),
        ]));

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($good));
        $result->addData(3, $this->row($bad));

        $this->save($queue, $result);

        $this->assertTrue($result->hasErrors());
        $this->assertSame(['address should not be blank'], $result->getSortedErrors()[3]);

        // Only the good one. Counted rather than matched on the object: Prophecy compares
        // objects loosely, and two tasks built the same way are equal to each other.
        $this->entityManager->persist(Argument::type(Task::class))->shouldHaveBeenCalledOnce();
        $this->assertNull($bad->getGroup());
    }

    /**
     * The row the error has to point at when saving fails
     */
    public function testTheRowBeingSavedIsReportedBack(): void
    {
        $queue = $this->createQueue();

        $task = $this->createTask();

        $result = new SpreadsheetParseResult();
        $result->addData(12, $this->row($task));

        $rowNumber = null;
        $this->handler->save($queue, $result, $rowNumber);

        $this->assertSame(12, $rowNumber);
    }
}
