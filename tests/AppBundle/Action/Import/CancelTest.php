<?php

declare(strict_types=1);

namespace Tests\AppBundle\Action\Import;

use AppBundle\Action\Import\Cancel;
use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Service\ImportStatusManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CancelTest extends TestCase
{
    use ProphecyTrait;

    private function createQueue(string $status): ImportQueue
    {
        $queue = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
        $queue->setStore(new Store());
        $queue->setFilename('deliveries.csv');
        $queue->setStatus($status);

        return $queue;
    }

    public function testPendingAndStartedImportsCanBeCancelled(): void
    {
        foreach ([ImportQueue::STATUS_PENDING, ImportQueue::STATUS_STARTED] as $status) {
            $queue = $this->createQueue($status);

            $statusManager = $this->prophesize(ImportStatusManager::class);
            $statusManager->cancel($queue)->willReturn(true);

            $action = new Cancel($statusManager->reveal(), new NullLogger());

            $this->assertSame($queue, $action($queue));

            $statusManager->cancel($queue)->shouldHaveBeenCalledOnce();
        }
    }

    public function testFinishedImportCanNotBeCancelled(): void
    {
        $statusManager = $this->prophesize(ImportStatusManager::class);

        $action = new Cancel($statusManager->reveal(), new NullLogger());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The import is completed, it can not be cancelled');

        try {
            $action($this->createQueue(ImportQueue::STATUS_COMPLETED));
        } finally {
            $statusManager->cancel(Argument::any())->shouldNotHaveBeenCalled();
        }
    }

    /**
     * Loaded while it was being read, the worker has moved on to saving it since
     */
    public function testImportSavedInTheMeantimeCanNotBeCancelled(): void
    {
        $queue = $this->createQueue(ImportQueue::STATUS_STARTED);

        $statusManager = $this->prophesize(ImportStatusManager::class);
        $statusManager->cancel($queue)->willReturn(false);
        $statusManager->currentStatus($queue)->willReturn(ImportQueue::STATUS_SAVING);

        $action = new Cancel($statusManager->reveal(), new NullLogger());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The import is saving, it can not be cancelled');

        $action($queue);
    }
}
