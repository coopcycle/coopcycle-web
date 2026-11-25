<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Import\ImportRegistry;
use AppBundle\Import\TaskImportHandler;
use AppBundle\Message\ImportTasksFromFile;
use AppBundle\MessageHandler\RunImportHandler;
use AppBundle\Service\ImportStatusManager;
use AppBundle\Service\LiveUpdatesSuspension;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A file of tasks through the same worker a file of deliveries goes through: against a
 * real database, as the groups and the assignments are written there and a rollback has
 * to undo them.
 */
class RunTaskImportFunctionalTest extends KernelTestCase
{
    use ProphecyTrait;

    const FILENAME = 'tasks.csv';

    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    private function createQueue(): ImportQueue
    {
        $queue = new ImportQueue(ImportQueue::TYPE_TASKS);
        $queue->setFilename(self::FILENAME);
        $queue->setStatus(ImportQueue::STATUS_PENDING);
        $queue->setOptions(['date' => '2026-11-25']);

        $this->entityManager->persist($queue);
        $this->entityManager->flush();

        return $queue;
    }

    private function createTask(string $day): Task
    {
        $address = new Address();
        $address->setStreetAddress('1, rue de Rivoli, Paris');
        $address->setGeo(new GeoCoordinates(48.856613, 2.352222));

        $task = new Task();
        $task->setAddress($address);
        $task->setAfter(new \DateTime(sprintf('%s 10:00', $day)));
        $task->setBefore(new \DateTime(sprintf('%s 12:00', $day)));

        return $task;
    }

    /**
     * @param array<int,array> $rows
     */
    private function createHandler(array $rows): RunImportHandler
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write(self::FILENAME, 'not read, the parser is doubled');

        $result = new SpreadsheetParseResult();
        foreach ($rows as $rowNumber => $row) {
            $result->addData($rowNumber, $row);
        }

        $parser = $this->prophesize(TaskSpreadsheetParser::class);
        $parser->parse(Argument::cetera())->willReturn($result);

        $importHandler = new TaskImportHandler(
            $this->entityManager,
            $filesystem,
            $parser->reveal(),
            self::getContainer()->get(ValidatorInterface::class),
            self::getContainer()->get(TaskListProvider::class)
        );

        $registry = new ImportRegistry(
            new ServiceLocator([ImportQueue::TYPE_TASKS => fn () => $importHandler]),
            new ServiceLocator([])
        );

        return new RunImportHandler(
            $this->entityManager,
            $registry,
            self::getContainer()->get(ImportStatusManager::class),
            self::getContainer()->get(LiveUpdatesSuspension::class),
            self::getContainer()->get(TranslatorInterface::class),
            new NullLogger(),
            self::getContainer()->get('doctrine'),
        );
    }

    public function testAFileOfTasksIsImported(): void
    {
        $queue = $this->createQueue();

        $handler = $this->createHandler([
            2 => ['task' => $this->createTask('2026-11-25'), 'groupName' => 'Monday round', 'assign' => null],
            3 => ['task' => $this->createTask('2026-11-26'), 'groupName' => null, 'assign' => null],
        ]);

        $handler(new ImportTasksFromFile(self::FILENAME, [], $queue->getId()));

        $this->assertSame(ImportQueue::STATUS_COMPLETED, $queue->getStatus());

        $tasks = $this->entityManager->getRepository(Task::class)->findAll();
        $this->assertCount(2, $tasks);

        foreach ($tasks as $task) {
            // Tagged with the import, which is how they are found again
            $this->assertSame($queue->getId(), $task->getMetadata()['task_import_id']);
            $this->assertNotNull($task->getGroup());
        }

        // The named group, and the one named after the import for the row that named none
        $groups = $this->entityManager->getRepository(TaskGroup::class)->findAll();
        $this->assertCount(2, $groups);
    }

    /**
     * Nothing is left behind when the file can't be saved, the groups above all: they
     * used to be written while the file was read, outside any transaction.
     */
    public function testNothingIsSavedWhenARowFails(): void
    {
        $queue = $this->createQueue();

        $good = $this->createTask('2026-11-25');

        // Valid as far as the validator is concerned, too long for the column: it fails
        // on the flush, which is what a deadlock or a timeout looks like
        $broken = $this->createTask('2026-11-26');
        $broken->setRef(str_repeat('x', 300));

        $handler = $this->createHandler([
            2 => ['task' => $good, 'groupName' => 'Monday round', 'assign' => null],
            3 => ['task' => $broken, 'groupName' => 'Tuesday round', 'assign' => null],
        ]);

        try {
            $handler(new ImportTasksFromFile(self::FILENAME, [], $queue->getId()));
            $this->fail('An exception should have been thrown');
        } catch (\Throwable $e) {
            // The message is not retried: a retry would fail the same way
        }

        $this->entityManager->clear();

        $this->assertCount(0, $this->entityManager->getRepository(Task::class)->findAll());
        $this->assertCount(0, $this->entityManager->getRepository(TaskGroup::class)->findAll());

        /** @var ImportQueue $queue */
        $queue = $this->entityManager->getRepository(ImportQueue::class)->findOneBy(['filename' => self::FILENAME]);
        $this->assertSame(ImportQueue::STATUS_FAILED, $queue->getStatus());
    }
}
