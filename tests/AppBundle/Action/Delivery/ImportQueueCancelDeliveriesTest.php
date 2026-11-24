<?php

declare(strict_types=1);

namespace Tests\AppBundle\Action\Delivery;

use AppBundle\Action\Delivery\ImportQueueCancelDeliveries;
use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Message\CancelImportedDeliveries;
use AppBundle\Service\DeliveryImportStatusManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ImportQueueCancelDeliveriesTest extends TestCase
{
    use ProphecyTrait;

    private function createQueue(string $status): DeliveryImportQueue
    {
        $queue = new DeliveryImportQueue();
        $queue->setStore(new Store());
        $queue->setFilename('deliveries.csv');
        $queue->setStatus($status);

        // Set by Doctrine otherwise
        $id = new \ReflectionProperty(DeliveryImportQueue::class, 'id');
        $id->setValue($queue, 18);

        return $queue;
    }

    public function testDeliveriesOfAFinishedImportAreCancelledByAWorker(): void
    {
        foreach ([DeliveryImportQueue::STATUS_COMPLETED, DeliveryImportQueue::STATUS_FAILED] as $status) {
            $queue = $this->createQueue($status);

            $statusManager = $this->prophesize(DeliveryImportStatusManager::class);
            $statusManager->cancellingDeliveries($queue)->willReturn(true);

            $messageBus = $this->prophesize(MessageBusInterface::class);
            $messageBus->dispatch(Argument::type(CancelImportedDeliveries::class))
                ->will(fn ($args) => new Envelope($args[0]));

            $action = new ImportQueueCancelDeliveries($statusManager->reveal(), $messageBus->reveal(), new NullLogger());

            $this->assertSame($queue, $action($queue));

            $messageBus->dispatch(new CancelImportedDeliveries(18))->shouldHaveBeenCalledOnce();
        }
    }

    public function testDeliveriesOfARunningImportAreNotCancelled(): void
    {
        $statusManager = $this->prophesize(DeliveryImportStatusManager::class);
        $messageBus = $this->prophesize(MessageBusInterface::class);

        $action = new ImportQueueCancelDeliveries($statusManager->reveal(), $messageBus->reveal(), new NullLogger());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The deliveries of an import that is saving can not be cancelled');

        try {
            $action($this->createQueue(DeliveryImportQueue::STATUS_SAVING));
        } finally {
            $messageBus->dispatch(Argument::any())->shouldNotHaveBeenCalled();
        }
    }

    /**
     * Loaded before another dispatcher asked
     */
    public function testDeliveriesAreNotCancelledTwice(): void
    {
        $queue = $this->createQueue(DeliveryImportQueue::STATUS_COMPLETED);

        $statusManager = $this->prophesize(DeliveryImportStatusManager::class);
        $statusManager->cancellingDeliveries($queue)->willReturn(false);
        $messageBus = $this->prophesize(MessageBusInterface::class);

        $action = new ImportQueueCancelDeliveries($statusManager->reveal(), $messageBus->reveal(), new NullLogger());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The deliveries of deliveries.csv are already being cancelled');

        try {
            $action($queue);
        } finally {
            $messageBus->dispatch(Argument::any())->shouldNotHaveBeenCalled();
        }
    }
}
