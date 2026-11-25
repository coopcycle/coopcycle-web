<?php

declare(strict_types=1);

namespace Tests\AppBundle\EventSubscriber;

use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Store;
use AppBundle\EventSubscriber\CancelImportedItemsFailureSubscriber;
use AppBundle\Message\CancelImportedItems;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\ImportStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class CancelImportedItemsFailureSubscriberTest extends TestCase
{
    use ProphecyTrait;

    private $statusManager;
    private ImportQueue $queue;
    private CancelImportedItemsFailureSubscriber $subscriber;

    public function setUp(): void
    {
        $this->queue = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
        $this->queue->setStore(new Store());
        $this->queue->setFilename('deliveries.csv');

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->isOpen()->willReturn(true);
        $entityManager->find(ImportQueue::class, 18)->willReturn($this->queue);

        $doctrine = $this->prophesize(ManagerRegistry::class);
        $doctrine->getManager()->willReturn($entityManager->reveal());

        $this->statusManager = $this->prophesize(ImportStatusManager::class);
        $this->statusManager->itemsCancellationFailed(Argument::cetera())->will(function () {});

        $this->subscriber = new CancelImportedItemsFailureSubscriber(
            $doctrine->reveal(),
            $this->statusManager->reveal(),
            new NullLogger()
        );
    }

    private function failedEvent($message, bool $willRetry): WorkerMessageFailedEvent
    {
        $event = new WorkerMessageFailedEvent(new Envelope($message), 'async', new \RuntimeException('Deadlock detected'));

        if ($willRetry) {
            $event->setForRetry();
        }

        return $event;
    }

    /**
     * The handler only goes through deliveries "cancelling"
     */
    public function testCancellationIsPutBackForTheRetry(): void
    {
        $this->subscriber->onFailure($this->failedEvent(new CancelImportedItems(18), true));

        $this->statusManager->itemsCancellationFailed($this->queue, true)->shouldHaveBeenCalledOnce();
    }

    /**
     * For a dispatcher to ask again
     */
    public function testCancellationGivenUpOnIsReset(): void
    {
        $this->subscriber->onFailure($this->failedEvent(new CancelImportedItems(18), false));

        $this->statusManager->itemsCancellationFailed($this->queue, false)->shouldHaveBeenCalledOnce();
    }

    public function testOtherMessagesAreIgnored(): void
    {
        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('deliveries.csv'), false));

        $this->statusManager->itemsCancellationFailed(Argument::cetera())->shouldNotHaveBeenCalled();
    }
}
