<?php

declare(strict_types=1);

namespace Tests\AppBundle\Command;

use AppBundle\Entity\ImportQueue;
use AppBundle\Exception\ImportRolledBackException;
use AppBundle\Import\ImportRunner;
use AppBundle\Service\ImportStatusManager;
use AppBundle\Transporter\ImportFailureNotifier;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\AppBundle\Command\Double\FakeDeliveriesImporter;

class ImportDeliveriesCommandTest extends KernelTestCase
{
    use ProphecyTrait;

    private function commandTester(): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find('coopcycle:deliveries:import'));
    }

    public function testImporterAndTransformerAreFoundByTag(): void
    {
        $tester = $this->commandTester();

        $tester->execute([
            'store' => '/api/stores/1',
            '--importer' => 'fake',
            '--transformer' => 'fake',
            '--dry-run' => true,
        ]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        // The transformer ran on the importer output
        $this->assertStringContainsString('dropoff.address,dropoff.comments', $output);
        $this->assertStringContainsString("\"FAKE COMMENT\nON TWO LINES\"", $output);
        // Counted as CSV records, not as lines
        $this->assertStringContainsString('>> 2 deliveries to import', $output);
        // Dry run: flush() was called, but told not to mutate anything
        $this->assertSame(
            [['dry_run' => true, 'rejected' => []]],
            self::getContainer()->get(FakeDeliveriesImporter::class)->flushCalls
        );
    }

    public function testFileThatCantBeTransformedIsRejectedAlone(): void
    {
        $tester = $this->commandTester();

        $importer = self::getContainer()->get(FakeDeliveriesImporter::class);
        $importer->files = [
            'broken.csv' => 'BROKEN',
            'valid.csv' => FakeDeliveriesImporter::VALID_FILE,
        ];

        $tester->execute([
            'store' => '/api/stores/1',
            '--importer' => 'fake',
            '--transformer' => 'fake',
            '--dry-run' => true,
        ]);

        // The valid file still goes through, the command fails for the broken one
        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Rejected file "broken.csv": Broken file', $output);
        $this->assertStringContainsString('>> 2 deliveries to import', $output);
        // The importer is told, so it can set it aside
        $this->assertSame(
            [['dry_run' => true, 'rejected' => ['broken.csv' => 'Broken file']]],
            $importer->flushCalls
        );
    }

    public function testRejectedFileIsSentToTheAdministrator(): void
    {
        $kernel = self::bootKernel();

        $notifier = $this->prophesize(ImportFailureNotifier::class);
        self::getContainer()->set(ImportFailureNotifier::class, $notifier->reveal());

        $importer = self::getContainer()->get(FakeDeliveriesImporter::class);
        $importer->files = [
            'broken.csv' => 'BROKEN',
        ];

        $tester = new CommandTester((new Application($kernel))->find('coopcycle:deliveries:import'));
        $tester->execute([
            'store' => '/api/stores/1',
            '--importer' => 'fake',
            '--transformer' => 'fake',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        // As received, before the transformer
        $notifier
            ->notify('fake', Argument::that(fn(array $files): bool =>
                array_keys($files) === ['broken.csv']
                && $files['broken.csv']->reason === 'Broken file'
                && $files['broken.csv']->content === 'BROKEN'
            ))
            ->shouldHaveBeenCalledOnce();
        $this->assertSame(
            [['dry_run' => false, 'rejected' => ['broken.csv' => 'Broken file']]],
            $importer->flushCalls
        );
    }

    /**
     * Loads a store, and puts a double in place of the importer, which ends each import the way $import says
     *
     * @param \Closure(ImportQueue): int $import Given the import, returns the number of deliveries saved
     * @return string The IRI of the store
     */
    private function importWith(\Closure $import): string
    {
        self::bootKernel();

        // Not self::getContainer() in the callback: Prophecy binds it to the method prophecy
        $container = self::getContainer();

        (new ORMPurger($container->get(EntityManagerInterface::class)))->purge();

        // With the default setup (channels, settings…): the tests that run after this one rely on it
        $fixtures = $container->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../fixtures/ORM/setup_default.yml',
            __DIR__.'/../../../fixtures/ORM/stores.yml'
        ], $_SERVER);

        // The notifier sends emails, it's checked on its own
        $container->set(ImportFailureNotifier::class, $this->prophesize(ImportFailureNotifier::class)->reveal());

        $importer = $this->prophesize(ImportRunner::class);
        $importer
            ->run(Argument::type('string'), ['create_task_if_address_not_geocoded' => true], Argument::type('int'))
            ->will(function (array $args) use ($import, $container): int {
                $queue = $container->get('doctrine')->getManager()
                    ->getRepository(ImportQueue::class)
                    ->findOneBy(['filename' => $args[0]]);

                $container->get(ImportStatusManager::class)->started($queue);

                return $import($queue);
            });
        $container->set(ImportRunner::class, $importer->reveal());

        return sprintf('/api/stores/%d', $fixtures['store_1']->getId());
    }

    private function execute(string $store): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('coopcycle:deliveries:import'));
        $tester->execute([
            'store' => $store,
            '--importer' => 'fake',
            '--transformer' => 'fake',
        ]);

        return $tester;
    }

    public function testImportedFileIsLetGoOf(): void
    {
        $store = $this->importWith(function (ImportQueue $queue): int {
            self::getContainer()->get(ImportStatusManager::class)->completed($queue);

            return 2;
        });

        $tester = $this->execute($store);

        $tester->assertCommandIsSuccessful();
        // What was saved, once the import is over
        $this->assertStringContainsString('>> "valid.csv": 2 deliveries imported, the import is completed', $tester->getDisplay());
        $this->assertSame(
            [['dry_run' => false, 'rejected' => []]],
            self::getContainer()->get(FakeDeliveriesImporter::class)->flushCalls
        );
    }

    /**
     * Saving the deliveries failed: the importer rolled them back, and marked the import as failed
     */
    public function testFileIsKeptWhenSavingItWasRolledBack(): void
    {
        $store = $this->importWith(function (ImportQueue $queue): int {
            self::getContainer()->get(ImportStatusManager::class)->failed($queue);
            throw new ImportRolledBackException('Deadlock detected');
        });

        $tester = $this->execute($store);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('>> "valid.csv": 0 deliveries imported, the import is failed', $tester->getDisplay());
        $this->assertStringContainsString('Deadlock detected', $tester->getDisplay());
        // To be pulled again on the next run
        $flushCalls = self::getContainer()->get(FakeDeliveriesImporter::class)->flushCalls;
        $this->assertSame(['valid.csv'], array_keys($flushCalls[0]['rejected']));
        $this->assertStringContainsString('Deadlock detected', $flushCalls[0]['rejected']['valid.csv']);
    }

    /**
     * Out of the worker, nothing else marks the import as failed
     */
    public function testImportThatFailedBeforeSavingIsMarkedAsFailed(): void
    {
        $store = $this->importWith(function (): int {
            throw new \RuntimeException('Could not read the file');
        });

        $tester = $this->execute($store);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('>> "valid.csv": 0 deliveries imported, the import is failed', $tester->getDisplay());
        $this->assertSame(
            ['valid.csv'],
            array_keys(self::getContainer()->get(FakeDeliveriesImporter::class)->flushCalls[0]['rejected'])
        );
    }

    public function testFileIsKeptWhenNoRowCouldBeSaved(): void
    {
        $store = $this->importWith(function (ImportQueue $queue): int {
            self::getContainer()->get(ImportStatusManager::class)->failed($queue, [
                ['row' => 2, 'errors' => ['dropoff.address is not valid']],
                ['row' => 3, 'errors' => ['dropoff.address is not valid']],
            ]);

            return 0;
        });

        $tester = $this->execute($store);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame(
            ['valid.csv' => 'No delivery could be imported, see the errors of the import'],
            self::getContainer()->get(FakeDeliveriesImporter::class)->flushCalls[0]['rejected']
        );
    }

    /**
     * Pulled again, the rows that were saved would be imported twice
     */
    public function testFileIsLetGoOfWhenSomeRowsWereSaved(): void
    {
        $store = $this->importWith(function (ImportQueue $queue): int {
            self::getContainer()->get(ImportStatusManager::class)->failed($queue, [
                ['row' => 3, 'errors' => ['dropoff.address is not valid']],
            ]);

            return 1;
        });

        $tester = $this->execute($store);

        // The import tells which rows failed
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('>> "valid.csv": 1 deliveries imported, the import is failed', $tester->getDisplay());
        $this->assertSame(
            [['dry_run' => false, 'rejected' => []]],
            self::getContainer()->get(FakeDeliveriesImporter::class)->flushCalls
        );
    }

    public function testFileGivenOnTheCommandLineThatCantBeTransformedFails(): void
    {
        $tester = $this->commandTester();

        $file = tempnam(sys_get_temp_dir(), 'coopcycle_import_test_');
        file_put_contents($file, 'BROKEN');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Broken file');

        try {
            $tester->execute([
                'store' => '/api/stores/1',
                'csv' => $file,
                '--transformer' => 'fake',
                '--dry-run' => true,
            ]);
        } finally {
            unlink($file);
        }
    }

    public function testCountWithAMalformedHeader(): void
    {
        $tester = $this->commandTester();

        // Empty trailing columns: the header has duplicate (empty) names
        $file = tempnam(sys_get_temp_dir(), 'coopcycle_import_test_');
        file_put_contents($file, "dropoff.address,dropoff.comments,,\n1 rue de la Paix,,,\n");

        try {
            $tester->execute([
                'store' => '/api/stores/1',
                'csv' => $file,
                '--dry-run' => true,
            ]);
        } finally {
            unlink($file);
        }

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('>> 1 deliveries to import', $tester->getDisplay());
    }

    public function testUnknownImporter(): void
    {
        $tester = $this->commandTester();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown importer "nope", available importers: "');

        $tester->execute([
            'store' => '/api/stores/1',
            '--importer' => 'nope',
            '--dry-run' => true,
        ]);
    }

    public function testUnknownTransformer(): void
    {
        $tester = $this->commandTester();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown transformer "nope", available transformers: "');

        $tester->execute([
            'store' => '/api/stores/1',
            '--importer' => 'fake',
            '--transformer' => 'nope',
            '--dry-run' => true,
        ]);
    }
}
