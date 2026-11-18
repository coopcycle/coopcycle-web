<?php

declare(strict_types=1);

namespace Tests\AppBundle\Action\Delivery;

use AppBundle\Action\Delivery\ImportQueueCancel;
use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Service\DeliveryImportStatusManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ImportQueueCancelTest extends TestCase
{
    use ProphecyTrait;

    private function createQueue(string $status): DeliveryImportQueue
    {
        $queue = new DeliveryImportQueue();
        $queue->setStore(new Store());
        $queue->setFilename('deliveries.csv');
        $queue->setStatus($status);

        return $queue;
    }

    public function testPendingAndStartedImportsCanBeCancelled(): void
    {
        foreach ([DeliveryImportQueue::STATUS_PENDING, DeliveryImportQueue::STATUS_STARTED] as $status) {
            $queue = $this->createQueue($status);

            $statusManager = $this->prophesize(DeliveryImportStatusManager::class);
            $statusManager->cancel($queue)->willReturn(true);

            $action = new ImportQueueCancel($statusManager->reveal(), new NullLogger());

            $this->assertSame($queue, $action($queue));

            $statusManager->cancel($queue)->shouldHaveBeenCalledOnce();
        }
    }

    public function testFinishedImportCanNotBeCancelled(): void
    {
        $statusManager = $this->prophesize(DeliveryImportStatusManager::class);

        $action = new ImportQueueCancel($statusManager->reveal(), new NullLogger());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The import is completed, it can not be cancelled');

        try {
            $action($this->createQueue(DeliveryImportQueue::STATUS_COMPLETED));
        } finally {
            $statusManager->cancel(Argument::any())->shouldNotHaveBeenCalled();
        }
    }

    /**
     * Loaded while it was being read, the worker has moved on to saving it since
     */
    public function testImportSavedInTheMeantimeCanNotBeCancelled(): void
    {
        $queue = $this->createQueue(DeliveryImportQueue::STATUS_STARTED);

        $statusManager = $this->prophesize(DeliveryImportStatusManager::class);
        $statusManager->cancel($queue)->willReturn(false);
        $statusManager->currentStatus($queue)->willReturn(DeliveryImportQueue::STATUS_SAVING);

        $action = new ImportQueueCancel($statusManager->reveal(), new NullLogger());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The import is saving, it can not be cancelled');

        $action($queue);
    }
}
