<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\User;
use AppBundle\Message\ImportTasks;
use AppBundle\MessageHandler\ImportTasksHandler;
use AppBundle\Service\LiveUpdates;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The dispatch board's import, and the one the API's /tasks/import_async leads to.
 *
 * It reports one error and imports nothing, where the import screen reports them row by
 * row: the parser hands both the same result, and this is the end that still gives up on
 * the first thing wrong with the file.
 */
class ImportTasksHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const FILENAME = 'tasks.csv';

    private $objectManager;
    private $parser;
    private $liveUpdates;
    private $taskListProvider;

    protected function setUp(): void
    {
        $this->objectManager = $this->prophesize(EntityManagerInterface::class);
        $this->objectManager->persist(Argument::any())->willReturn(null);
        $this->objectManager->flush()->willReturn(null);

        $this->parser = $this->prophesize(TaskSpreadsheetParser::class);
        $this->liveUpdates = $this->prophesize(LiveUpdates::class);
        $this->taskListProvider = $this->prophesize(TaskListProvider::class);
    }

    protected function tearDown(): void
    {
        // The handler disables them for the whole process
        RemotePushNotificationManager::enable();

        parent::tearDown();
    }

    private function createHandler(): ImportTasksHandler
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write(self::FILENAME, 'not read, the parser is doubled');

        $validator = $this->prophesize(ValidatorInterface::class);
        $validator->validate(Argument::any())->willReturn(new ConstraintViolationList());

        return new ImportTasksHandler(
            $this->objectManager->reveal(),
            $filesystem,
            $this->parser->reveal(),
            $validator->reveal(),
            $this->liveUpdates->reveal(),
            new NullLogger(),
            $this->taskListProvider->reveal()
        );
    }

    private function message(): ImportTasks
    {
        return new ImportTasks('t0ken', self::FILENAME, new \DateTime('2026-11-25'));
    }

    private function row(Task $task, ?string $groupName = null, ?array $assign = null): array
    {
        return ['task' => $task, 'groupName' => $groupName, 'assign' => $assign];
    }

    public function testTheFileIsGivenUpOnAtTheFirstThingWrongWithIt(): void
    {
        $result = new SpreadsheetParseResult();
        $result->addErrorToRow(3, 'Could not geocode address');
        $result->addErrorToRow(7, 'A later row, reported too');

        $this->parser->parse(Argument::cetera())->willReturn($result);

        $handler = $this->createHandler();
        $handler($this->message());

        // The first error, as the dispatch board and the API have always reported
        $this->liveUpdates->toDispatchers('task_import:failure', [
            'token' => 't0ken',
            'message' => 'Could not geocode address',
        ])->shouldHaveBeenCalled();

        $this->liveUpdates->toDispatchers('task_import:success', Argument::any())->shouldNotHaveBeenCalled();
        $this->objectManager->persist(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * The parser hands the group's name over rather than making the group itself, so that
     * nothing is written before the rows are known to be good
     */
    public function testTheGroupsOfTheFileAreMadeHere(): void
    {
        $first = new Task();
        $second = new Task();
        $third = new Task();

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($first, 'Monday round'));
        $result->addData(3, $this->row($second, 'Monday round'));
        $result->addData(4, $this->row($third, 'Tuesday round'));

        $this->parser->parse(Argument::cetera())->willReturn($result);

        $handler = $this->createHandler();
        $handler($this->message());

        // One group per name, shared by the rows that name it
        $this->assertSame('Monday round', $first->getGroup()->getName());
        $this->assertSame($first->getGroup(), $second->getGroup());
        $this->assertSame('Tuesday round', $third->getGroup()->getName());

        $this->liveUpdates->toDispatchers('task_import:success', ['token' => 't0ken'])->shouldHaveBeenCalled();
    }

    /**
     * A row that names no group falls into the one group named after the import itself
     */
    public function testRowsWithNoGroupShareTheImportsOwnGroup(): void
    {
        $first = new Task();
        $second = new Task();

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($first));
        $result->addData(3, $this->row($second));

        $this->parser->parse(Argument::cetera())->willReturn($result);

        $handler = $this->createHandler();
        $handler($this->message());

        $this->assertInstanceOf(TaskGroup::class, $first->getGroup());
        $this->assertSame($first->getGroup(), $second->getGroup());
        $this->assertStringStartsWith('Import ', $first->getGroup()->getName());
    }

    /**
     * Appending a task to a courier's list writes to a list that is already saved, which
     * the parser no longer does: it says who the row names, and this applies it.
     */
    public function testTheAssignmentOfARowIsApplied(): void
    {
        $courier = new User();
        $date = new \DateTime('2026-11-26');

        $taskList = new TaskList();
        $taskList->setDate($date);
        $taskList->setCourier($courier);

        $this->taskListProvider->getTaskListForUserAndDate($date, $courier)->willReturn($taskList);

        $task = new Task();

        $result = new SpreadsheetParseResult();
        $result->addData(2, $this->row($task, null, [$courier, $date]));

        $this->parser->parse(Argument::cetera())->willReturn($result);

        $handler = $this->createHandler();
        $handler($this->message());

        $this->assertTrue($task->isAssigned());
        $this->assertTrue($task->isAssignedTo($courier));
    }
}
