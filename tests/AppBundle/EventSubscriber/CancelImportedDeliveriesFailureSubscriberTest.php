<?php

declare(strict_types=1);

namespace Tests\AppBundle\EventSubscriber;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Store;
use AppBundle\EventSubscriber\CancelImportedDeliveriesFailureSubscriber;
use AppBundle\Message\CancelImportedDeliveries;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\DeliveryImportStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class CancelImportedDeliveriesFailureSubscriberTest extends TestCase
{
    use ProphecyTrait;

    private $statusManager;
    private DeliveryImportQueue $queue;
    private CancelImportedDeliveriesFailureSubscriber $subscriber;

    public function setUp(): void
    {
        $this->queue = new DeliveryImportQueue();
        $this->queue->setStore(new Store());
        $this->queue->setFilename('deliveries.csv');

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->isOpen()->willReturn(true);
        $entityManager->find(DeliveryImportQueue::class, 18)->willReturn($this->queue);

        $doctrine = $this->prophesize(ManagerRegistry::class);
        $doctrine->getManager()->willReturn($entityManager->reveal());

        $this->statusManager = $this->prophesize(DeliveryImportStatusManager::class);
        $this->statusManager->deliveriesCancellationFailed(Argument::cetera())->will(function () {});

        $this->subscriber = new CancelImportedDeliveriesFailureSubscriber(
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
        $this->subscriber->onFailure($this->failedEvent(new CancelImportedDeliveries(18), true));

        $this->statusManager->deliveriesCancellationFailed($this->queue, true)->shouldHaveBeenCalledOnce();
    }

    /**
     * For a dispatcher to ask again
     */
    public function testCancellationGivenUpOnIsReset(): void
    {
        $this->subscriber->onFailure($this->failedEvent(new CancelImportedDeliveries(18), false));

        $this->statusManager->deliveriesCancellationFailed($this->queue, false)->shouldHaveBeenCalledOnce();
    }

    public function testOtherMessagesAreIgnored(): void
    {
        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('deliveries.csv'), false));

        $this->statusManager->deliveriesCancellationFailed(Argument::cetera())->shouldNotHaveBeenCalled();
    }
}
