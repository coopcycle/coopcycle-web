<?php

declare(strict_types=1);

namespace Tests\AppBundle\Action\Import;

use AppBundle\Action\Import\CancelItems;
use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Message\CancelImportedItems;
use AppBundle\Service\ImportStatusManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CancelItemsTest extends TestCase
{
    use ProphecyTrait;

    private function createQueue(string $status): ImportQueue
    {
        $queue = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
        $queue->setStore(new Store());
        $queue->setFilename('deliveries.csv');
        $queue->setStatus($status);

        // Set by Doctrine otherwise
        $id = new \ReflectionProperty(ImportQueue::class, 'id');
        $id->setValue($queue, 18);

        return $queue;
    }

    public function testWhatAFinishedImportCreatedIsCancelledByAWorker(): void
    {
        foreach ([ImportQueue::STATUS_COMPLETED, ImportQueue::STATUS_FAILED] as $status) {
            $queue = $this->createQueue($status);

            $statusManager = $this->prophesize(ImportStatusManager::class);
            $statusManager->cancellingItems($queue)->willReturn(true);

            $messageBus = $this->prophesize(MessageBusInterface::class);
            $messageBus->dispatch(Argument::type(CancelImportedItems::class))
                ->will(fn ($args) => new Envelope($args[0]));

            $action = new CancelItems($statusManager->reveal(), $messageBus->reveal(), new NullLogger());

            $this->assertSame($queue, $action($queue));

            $messageBus->dispatch(new CancelImportedItems(18))->shouldHaveBeenCalledOnce();
        }
    }

    public function testWhatARunningImportCreatedIsNotCancelled(): void
    {
        $statusManager = $this->prophesize(ImportStatusManager::class);
        $messageBus = $this->prophesize(MessageBusInterface::class);

        $action = new CancelItems($statusManager->reveal(), $messageBus->reveal(), new NullLogger());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('What an import that is saving created can not be cancelled');

        try {
            $action($this->createQueue(ImportQueue::STATUS_SAVING));
        } finally {
            $messageBus->dispatch(Argument::any())->shouldNotHaveBeenCalled();
        }
    }

    /**
     * Loaded before another dispatcher asked
     */
    public function testWhatAnImportCreatedIsNotCancelledTwice(): void
    {
        $queue = $this->createQueue(ImportQueue::STATUS_COMPLETED);

        $statusManager = $this->prophesize(ImportStatusManager::class);
        $statusManager->cancellingItems($queue)->willReturn(false);
        $messageBus = $this->prophesize(MessageBusInterface::class);

        $action = new CancelItems($statusManager->reveal(), $messageBus->reveal(), new NullLogger());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('What deliveries.csv created is already being cancelled');

        try {
            $action($queue);
        } finally {
            $messageBus->dispatch(Argument::any())->shouldNotHaveBeenCalled();
        }
    }
}
