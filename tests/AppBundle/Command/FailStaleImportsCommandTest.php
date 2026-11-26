<?php

namespace Tests\AppBundle\Command;

use AppBundle\Entity\ImportQueue;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FailStaleImportsCommandTest extends KernelTestCase
{
    private EntityManager $entityManager;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->beginTransaction();

        $command = (new Application(self::$kernel))
            ->find('coopcycle:imports:fail-stale');

        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    /**
     * The deployments' cron entries call the command by the name it had when it only
     * swept delivery imports: renaming it without keeping that name would stop the
     * sweep everywhere, quietly.
     */
    public function testTheNameTheCronUsesStillResolves()
    {
        $command = (new Application(self::$kernel))
            ->find('coopcycle:deliveries:fail-stale-imports');

        $this->assertSame('coopcycle:imports:fail-stale', $command->getName());
    }

    /**
     * Timestampable overwrites createdAt on persist, so the age is set once the
     * row is written, with a DQL update that goes around the listeners.
     */
    private function createImport(string $filename, string $status, int $createdMinutesAgo, ?int $startedMinutesAgo): int
    {
        $queue = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
        $queue->setFilename($filename);
        $queue->setStatus($status);

        $this->entityManager->persist($queue);
        $this->entityManager->flush();

        $this->entityManager
            ->createQuery(
                'UPDATE '.ImportQueue::class.' q '.
                'SET q.createdAt = :createdAt, q.startedAt = :startedAt WHERE q.id = :id'
            )
            ->setParameter('createdAt', new \DateTime(sprintf('-%d minutes', $createdMinutesAgo)))
            ->setParameter('startedAt', null === $startedMinutesAgo
                ? null
                : new \DateTime(sprintf('-%d minutes', $startedMinutesAgo)))
            ->setParameter('id', $queue->getId())
            ->execute();

        return $queue->getId();
    }

    private function setProgressedAt(int $id, int $minutesAgo): void
    {
        $this->entityManager
            ->createQuery('UPDATE '.ImportQueue::class.' q SET q.progressedAt = :progressedAt WHERE q.id = :id')
            ->setParameter('progressedAt', new \DateTime(sprintf('-%d minutes', $minutesAgo)))
            ->setParameter('id', $id)
            ->execute();
    }

    private function reload(int $id): ImportQueue
    {
        // Read the row back rather than whatever the command left in memory
        $this->entityManager->clear();

        return $this->entityManager->find(ImportQueue::class, $id);
    }

    private function statusOf(int $id): string
    {
        return $this->reload($id)->getStatus();
    }

    public function testOnlyTheImportsStuckForLongEnoughAreFailed(): void
    {
        $staleStarted = $this->createImport('stale-started.csv', ImportQueue::STATUS_STARTED, 180, 180);
        $freshStarted = $this->createImport('fresh-started.csv', ImportQueue::STATUS_STARTED, 5, 5);
        // Never picked up by a worker, so it has no startedAt to go by
        $stalePending = $this->createImport('stale-pending.csv', ImportQueue::STATUS_PENDING, 180, null);
        $freshPending = $this->createImport('fresh-pending.csv', ImportQueue::STATUS_PENDING, 2, null);
        // Queued a long time ago, but a worker is handling it right now
        $longRunning = $this->createImport('long-running.csv', ImportQueue::STATUS_STARTED, 180, 5);
        // The worker died while saving the deliveries
        $staleSaving = $this->createImport('stale-saving.csv', ImportQueue::STATUS_SAVING, 180, 180);

        $this->entityManager->clear();

        $this->commandTester->execute([]);

        $this->assertSame(ImportQueue::STATUS_FAILED, $this->statusOf($staleStarted));
        $this->assertSame(ImportQueue::STATUS_FAILED, $this->statusOf($stalePending));
        $this->assertSame(ImportQueue::STATUS_FAILED, $this->statusOf($staleSaving));

        $this->assertSame(ImportQueue::STATUS_STARTED, $this->statusOf($freshStarted));
        $this->assertSame(ImportQueue::STATUS_PENDING, $this->statusOf($freshPending));
        $this->assertSame(ImportQueue::STATUS_STARTED, $this->statusOf($longRunning));
    }

    public function testAnImportThatKeepsProgressingIsLeftAlone(): void
    {
        // Started hours ago on a large file, still reporting its progress
        $progressing = $this->createImport('large.csv', ImportQueue::STATUS_STARTED, 300, 300);
        $this->setProgressedAt($progressing, 1);

        // Started hours ago too, but stopped reporting long ago
        $stalled = $this->createImport('stalled.csv', ImportQueue::STATUS_STARTED, 300, 300);
        $this->setProgressedAt($stalled, 120);

        $this->entityManager->clear();

        $this->commandTester->execute([]);

        $this->assertSame(ImportQueue::STATUS_STARTED, $this->statusOf($progressing));
        $this->assertSame(ImportQueue::STATUS_FAILED, $this->statusOf($stalled));
    }

    public function testAFailedImportIsGivenAReason(): void
    {
        $queue = $this->createImport('stale.csv', ImportQueue::STATUS_STARTED, 180, 180);

        $this->entityManager->clear();

        $this->commandTester->execute([]);

        $errors = $this->reload($queue)->getErrors();

        $this->assertNotEmpty($errors);
        $this->assertSame(0, $errors[0]['row']);
        $this->assertNotEmpty($errors[0]['errors'][0]);
    }

    public function testFinishedImportsAreLeftAlone(): void
    {
        $completed = $this->createImport('done.csv', ImportQueue::STATUS_COMPLETED, 180, 180);

        $this->entityManager->clear();

        $this->commandTester->execute([]);

        $this->assertSame(ImportQueue::STATUS_COMPLETED, $this->statusOf($completed));
    }

    public function testDryRunWritesNothing(): void
    {
        $queue = $this->createImport('stale.csv', ImportQueue::STATUS_STARTED, 180, 180);

        $this->entityManager->clear();

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(ImportQueue::STATUS_STARTED, $this->statusOf($queue));
        $this->assertStringContainsString('stale.csv', $this->commandTester->getDisplay());
    }

    public function testThresholdIsConfigurable(): void
    {
        $queue = $this->createImport('ten-minutes.csv', ImportQueue::STATUS_STARTED, 10, 10);

        $this->entityManager->clear();

        $this->commandTester->execute(['--older-than' => 60]);
        $this->assertSame(ImportQueue::STATUS_STARTED, $this->statusOf($queue));

        $this->commandTester->execute(['--older-than' => 5]);
        $this->assertSame(ImportQueue::STATUS_FAILED, $this->statusOf($queue));
    }

    public function testAThresholdOfZeroIsRejected(): void
    {
        $this->assertSame(
            \Symfony\Component\Console\Command\Command::INVALID,
            $this->commandTester->execute(['--older-than' => 0])
        );
    }

    /**
     * Nobody ever came back to say which field each column of the file holds, so no worker
     * will ever pick it up. Cancelled rather than failed: nothing of it was even read.
     */
    public function testAnImportNobodyCameBackToMapIsCancelled(): void
    {
        $abandoned = $this->createImport('abandoned.csv', ImportQueue::STATUS_MAPPING, 60 * 48, null);
        $beingMapped = $this->createImport('being-mapped.csv', ImportQueue::STATUS_MAPPING, 5, null);

        $this->entityManager->clear();

        $this->commandTester->execute([]);

        $this->assertSame(ImportQueue::STATUS_CANCELLED, $this->statusOf($abandoned));
        $this->assertSame(ImportQueue::STATUS_MAPPING, $this->statusOf($beingMapped));
    }

    /**
     * Someone mapping a file over a long lunch is not a stale import: it waits for a
     * person, not for a worker, and gets far longer than the worker threshold
     */
    public function testAnImportWaitingToBeMappedOutlivesTheWorkerThreshold(): void
    {
        $queue = $this->createImport('lunch.csv', ImportQueue::STATUS_MAPPING, 120, null);

        $this->entityManager->clear();

        $this->commandTester->execute([]);

        $this->assertSame(ImportQueue::STATUS_MAPPING, $this->statusOf($queue));
    }

    public function testTheMappingThresholdIsConfigurable(): void
    {
        $queue = $this->createImport('lunch.csv', ImportQueue::STATUS_MAPPING, 120, null);

        $this->entityManager->clear();

        $this->commandTester->execute(['--mapping-older-than' => 180]);
        $this->assertSame(ImportQueue::STATUS_MAPPING, $this->statusOf($queue));

        $this->commandTester->execute(['--mapping-older-than' => 60]);
        $this->assertSame(ImportQueue::STATUS_CANCELLED, $this->statusOf($queue));
    }

    public function testAnImportWaitingToBeMappedIsLeftAloneOnADryRun(): void
    {
        $queue = $this->createImport('abandoned.csv', ImportQueue::STATUS_MAPPING, 60 * 48, null);

        $this->entityManager->clear();

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(ImportQueue::STATUS_MAPPING, $this->statusOf($queue));
        $this->assertStringContainsString('abandoned.csv', $this->commandTester->getDisplay());
    }

    public function testAMappingThresholdOfZeroIsRejected(): void
    {
        $this->assertSame(
            \Symfony\Component\Console\Command\Command::INVALID,
            $this->commandTester->execute(['--mapping-older-than' => 0])
        );
    }
}
