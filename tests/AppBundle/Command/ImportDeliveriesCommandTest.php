<?php

declare(strict_types=1);

namespace Tests\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\AppBundle\Command\Double\FakeDeliveriesImporter;

class ImportDeliveriesCommandTest extends KernelTestCase
{
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
        $this->assertStringContainsString('>> 2 deliveries imported', $output);
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
        $this->assertStringContainsString('>> 2 deliveries imported', $output);
        // The importer is told, so it can set it aside
        $this->assertSame(
            [['dry_run' => true, 'rejected' => ['broken.csv' => 'Broken file']]],
            $importer->flushCalls
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
        $this->assertStringContainsString('>> 1 deliveries imported', $tester->getDisplay());
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
