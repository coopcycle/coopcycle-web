<?php

declare(strict_types=1);

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Service\DeliveryImportStatusManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The worker importing a file and the admin cancelling it are two processes,
 * each with its own copy of the import in memory: what they tell each other
 * goes through the database.
 */
class DeliveryImportStatusManagerTest extends KernelTestCase
{
    private EntityManager $entityManager;
    private DeliveryImportStatusManager $statusManager;

    protected function setUp(): void
    {
        // SET UP SYMFONY
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->beginTransaction();

        $this->statusManager = self::getContainer()->get(DeliveryImportStatusManager::class);
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    private function createImport(string $status): DeliveryImportQueue
    {
        $queue = new DeliveryImportQueue();
        $queue->setFilename('deliveries.csv');
        $queue->setStatus($status);

        $this->entityManager->persist($queue);
        $this->entityManager->flush();

        return $queue;
    }

    private function fetchRow(DeliveryImportQueue $queue): array
    {
        return $this->entityManager->getConnection()->fetchAssociative(
            'SELECT status, total_rows, processed_rows, error_rows, progressed_at FROM delivery_import_queue WHERE id = ?',
            [$queue->getId()]
        );
    }

    public function testProgressIsWrittenWithoutAFlush(): void
    {
        $queue = $this->createImport(DeliveryImportQueue::STATUS_STARTED);

        // Something else the worker has in the unit of work, halfway through the file
        $other = new DeliveryImportQueue();
        $other->setFilename('other.csv');
        $this->entityManager->persist($other);

        $this->statusManager->progress($queue, 120, 15000, 3);

        $row = $this->fetchRow($queue);

        $this->assertSame(120, $row['processed_rows']);
        $this->assertSame(15000, $row['total_rows']);
        $this->assertSame(3, $row['error_rows']);
        $this->assertNotNull($row['progressed_at']);

        $this->assertNull($other->getId());
    }

    public function testCancellationIsReadFromTheDatabase(): void
    {
        $queue = $this->createImport(DeliveryImportQueue::STATUS_STARTED);

        $this->assertFalse($this->statusManager->isCancelled($queue));

        // As the admin would, behind the back of the worker's copy
        $this->entityManager->getConnection()->update(
            'delivery_import_queue',
            ['status' => DeliveryImportQueue::STATUS_CANCELLED],
            ['id' => $queue->getId()]
        );

        $this->assertSame(DeliveryImportQueue::STATUS_STARTED, $queue->getStatus());
        $this->assertTrue($this->statusManager->isCancelled($queue));
    }

    public function testPendingOrStartedImportCanBeCancelled(): void
    {
        foreach ([DeliveryImportQueue::STATUS_PENDING, DeliveryImportQueue::STATUS_STARTED] as $status) {
            $queue = $this->createImport($status);

            $this->assertTrue($this->statusManager->cancel($queue));

            $this->assertSame(DeliveryImportQueue::STATUS_CANCELLED, $this->fetchRow($queue)['status']);
            $this->assertFalse($queue->isCancellable());
        }
    }

    /**
     * The dispatcher and the worker race: whichever writes first wins, and the
     * other one is told. Before, a cancel landing while the deliveries were being
     * saved left an import marked as cancelled with its deliveries created.
     */
    public function testCancelAndSavingExcludeEachOther(): void
    {
        $cancelledFirst = $this->createImport(DeliveryImportQueue::STATUS_STARTED);
        $this->assertTrue($this->statusManager->cancel($cancelledFirst));
        $this->assertFalse($this->statusManager->saving($cancelledFirst));
        $this->assertSame(DeliveryImportQueue::STATUS_CANCELLED, $this->fetchRow($cancelledFirst)['status']);

        $savingFirst = $this->createImport(DeliveryImportQueue::STATUS_STARTED);
        $this->assertTrue($this->statusManager->saving($savingFirst));
        $this->assertFalse($this->statusManager->cancel($savingFirst));
        $this->assertSame(DeliveryImportQueue::STATUS_SAVING, $this->fetchRow($savingFirst)['status']);
    }

    public function testFinishedImportCanNotBeCancelled(): void
    {
        $queue = $this->createImport(DeliveryImportQueue::STATUS_COMPLETED);

        $this->assertFalse($this->statusManager->cancel($queue));
        $this->assertSame(DeliveryImportQueue::STATUS_COMPLETED, $this->fetchRow($queue)['status']);
    }

    /**
     * Two dispatchers asking at the same time would send two workers
     */
    public function testDeliveriesOfAnImportAreCancelledOnce(): void
    {
        $queue = $this->createImport(DeliveryImportQueue::STATUS_COMPLETED);

        $this->assertTrue($this->statusManager->cancellingDeliveries($queue));
        $this->assertFalse($this->statusManager->cancellingDeliveries($queue));

        $this->assertSame(
            DeliveryImportQueue::DELIVERIES_CANCELLING,
            $this->entityManager->getConnection()->fetchOne('SELECT deliveries_cancellation FROM delivery_import_queue WHERE id = ?', [$queue->getId()])
        );
    }

    public function testDeliveriesOfAnImportStillRunningAreNotCancelled(): void
    {
        foreach ([DeliveryImportQueue::STATUS_PENDING, DeliveryImportQueue::STATUS_STARTED, DeliveryImportQueue::STATUS_SAVING] as $status) {
            $this->assertFalse($this->statusManager->cancellingDeliveries($this->createImport($status)), $status);
        }
    }

    public function testDeliveriesCancellationIsWrittenWithoutAFlush(): void
    {
        $queue = $this->createImport(DeliveryImportQueue::STATUS_COMPLETED);
        $this->statusManager->cancellingDeliveries($queue);

        // The worker clears the entity manager as it goes
        $this->entityManager->clear();

        $this->statusManager->deliveriesCancelled($queue, 30, 28, 2);

        $this->assertEquals(
            ['deliveries_cancellation' => 'cancelled', 'deliveries_to_cancel' => 30, 'cancelled_deliveries' => 28, 'skipped_deliveries' => 2],
            $this->entityManager->getConnection()->fetchAssociative(
                'SELECT deliveries_cancellation, deliveries_to_cancel, cancelled_deliveries, skipped_deliveries FROM delivery_import_queue WHERE id = ?',
                [$queue->getId()]
            )
        );
    }

    public function testFailedCancellationCanBeAskedAgain(): void
    {
        $queue = $this->createImport(DeliveryImportQueue::STATUS_COMPLETED);
        $this->statusManager->cancellingDeliveries($queue);

        $this->statusManager->deliveriesCancellationFailed($queue, false);

        $this->assertTrue($this->statusManager->cancellingDeliveries($queue));
    }
}
