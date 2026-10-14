<?php

namespace Tests\AppBundle\Command;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FailStaleDeliveryImportsCommandTest extends KernelTestCase
{
    private EntityManager $entityManager;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->beginTransaction();

        $command = (new Application(self::$kernel))
            ->find('coopcycle:deliveries:fail-stale-imports');

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
     * Timestampable overwrites createdAt on persist, so the age is set once the
     * row is written, with a DQL update that goes around the listeners.
     */
    private function createImport(string $filename, string $status, int $createdMinutesAgo, ?int $startedMinutesAgo): int
    {
        $queue = new DeliveryImportQueue();
        $queue->setFilename($filename);
        $queue->setStatus($status);

        $this->entityManager->persist($queue);
        $this->entityManager->flush();

        $this->entityManager
            ->createQuery(
                'UPDATE '.DeliveryImportQueue::class.' q '.
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

    private function reload(int $id): DeliveryImportQueue
    {
        // Read the row back rather than whatever the command left in memory
        $this->entityManager->clear();

        return $this->entityManager->find(DeliveryImportQueue::class, $id);
    }

    private function statusOf(int $id): string
    {
        return $this->reload($id)->getStatus();
    }

    public function testOnlyTheImportsStuckForLongEnoughAreFailed(): void
    {
        $staleStarted = $this->createImport('stale-started.csv', DeliveryImportQueue::STATUS_STARTED, 180, 180);
        $freshStarted = $this->createImport('fresh-started.csv', DeliveryImportQueue::STATUS_STARTED, 5, 5);
        // Never picked up by a worker, so it has no startedAt to go by
        $stalePending = $this->createImport('stale-pending.csv', DeliveryImportQueue::STATUS_PENDING, 180, null);
        $freshPending = $this->createImport('fresh-pending.csv', DeliveryImportQueue::STATUS_PENDING, 2, null);
        // Queued a long time ago, but a worker is handling it right now
        $longRunning = $this->createImport('long-running.csv', DeliveryImportQueue::STATUS_STARTED, 180, 5);

        $this->entityManager->clear();

        $this->commandTester->execute([]);

        $this->assertSame(DeliveryImportQueue::STATUS_FAILED, $this->statusOf($staleStarted));
        $this->assertSame(DeliveryImportQueue::STATUS_FAILED, $this->statusOf($stalePending));

        $this->assertSame(DeliveryImportQueue::STATUS_STARTED, $this->statusOf($freshStarted));
        $this->assertSame(DeliveryImportQueue::STATUS_PENDING, $this->statusOf($freshPending));
        $this->assertSame(DeliveryImportQueue::STATUS_STARTED, $this->statusOf($longRunning));
    }

    public function testAFailedImportIsGivenAReason(): void
    {
        $queue = $this->createImport('stale.csv', DeliveryImportQueue::STATUS_STARTED, 180, 180);

        $this->entityManager->clear();

        $this->commandTester->execute([]);

        $errors = $this->reload($queue)->getErrors();

        $this->assertNotEmpty($errors);
        $this->assertSame(0, $errors[0]['row']);
        $this->assertNotEmpty($errors[0]['errors'][0]);
    }

    public function testFinishedImportsAreLeftAlone(): void
    {
        $completed = $this->createImport('done.csv', DeliveryImportQueue::STATUS_COMPLETED, 180, 180);

        $this->entityManager->clear();

        $this->commandTester->execute([]);

        $this->assertSame(DeliveryImportQueue::STATUS_COMPLETED, $this->statusOf($completed));
    }

    public function testDryRunWritesNothing(): void
    {
        $queue = $this->createImport('stale.csv', DeliveryImportQueue::STATUS_STARTED, 180, 180);

        $this->entityManager->clear();

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(DeliveryImportQueue::STATUS_STARTED, $this->statusOf($queue));
        $this->assertStringContainsString('stale.csv', $this->commandTester->getDisplay());
    }

    public function testThresholdIsConfigurable(): void
    {
        $queue = $this->createImport('ten-minutes.csv', DeliveryImportQueue::STATUS_STARTED, 10, 10);

        $this->entityManager->clear();

        $this->commandTester->execute(['--older-than' => 60]);
        $this->assertSame(DeliveryImportQueue::STATUS_STARTED, $this->statusOf($queue));

        $this->commandTester->execute(['--older-than' => 5]);
        $this->assertSame(DeliveryImportQueue::STATUS_FAILED, $this->statusOf($queue));
    }

    public function testAThresholdOfZeroIsRejected(): void
    {
        $this->assertSame(
            \Symfony\Component\Console\Command\Command::INVALID,
            $this->commandTester->execute(['--older-than' => 0])
        );
    }
}
