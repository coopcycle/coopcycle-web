<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Exception\ImportRolledBackException;
use AppBundle\Import\ImportRunner;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Message\ImportTasksFromFile;
use AppBundle\MessageHandler\RunImportHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class RunImportHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function testFileIsImportedWithTheOptionsOfTheMessage(): void
    {
        $runner = $this->prophesize(ImportRunner::class);
        $runner->run('deliveries.csv', ['create_task_if_address_not_geocoded' => true], 42)->willReturn(2);

        $handler = new RunImportHandler($runner->reveal());
        $handler(new ImportDeliveries('deliveries.csv', ['create_task_if_address_not_geocoded' => true], 42));

        $runner->run('deliveries.csv', ['create_task_if_address_not_geocoded' => true], 42)
            ->shouldHaveBeenCalledOnce();
    }

    /**
     * Whatever the file holds: the same worker runs them all
     */
    public function testAFileOfTasksIsImportedByTheSameHandler(): void
    {
        $runner = $this->prophesize(ImportRunner::class);
        $runner->run('tasks.csv', [], 7)->willReturn(3);

        $handler = new RunImportHandler($runner->reveal());
        $handler(new ImportTasksFromFile('tasks.csv', [], 7));

        $runner->run('tasks.csv', [], 7)->shouldHaveBeenCalledOnce();
    }

    /**
     * Nothing was imported, and a retry would most likely fail the same way
     */
    public function testImportThatWasRolledBackIsNotRetried(): void
    {
        $runner = $this->prophesize(ImportRunner::class);
        $runner->run('deliveries.csv', [], null)->willThrow(
            new ImportRolledBackException('Importing deliveries.csv failed: Deadlock detected')
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Importing deliveries.csv failed: Deadlock detected');

        $handler = new RunImportHandler($runner->reveal());
        $handler(new ImportDeliveries('deliveries.csv'));
    }

    /**
     * Before anything was saved (a timeout, say): the worker retries it,
     * see ImportFailureSubscriber
     */
    public function testOtherFailuresAreRetried(): void
    {
        $runner = $this->prophesize(ImportRunner::class);
        $runner->run('deliveries.csv', [], null)->willThrow(new \RuntimeException('Could not read the file'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not read the file');

        $handler = new RunImportHandler($runner->reveal());
        $handler(new ImportDeliveries('deliveries.csv'));
    }
}
