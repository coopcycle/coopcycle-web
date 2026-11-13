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
        $this->assertSame([true], self::getContainer()->get(FakeDeliveriesImporter::class)->flushCalls);
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
