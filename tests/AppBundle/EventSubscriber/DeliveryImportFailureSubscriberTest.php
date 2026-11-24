<?php

namespace Tests\AppBundle\EventSubscriber;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\EventSubscriber\DeliveryImportFailureSubscriber;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Message\ImportTasks;
use AppBundle\Service\DeliveryImportStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeliveryImportFailureSubscriberTest extends TestCase
{
    use ProphecyTrait;

    private $doctrine;
    private $entityManager;
    private $repository;
    private $statusManager;
    private DeliveryImportFailureSubscriber $subscriber;

    public function setUp(): void
    {
        $this->repository = $this->prophesize(ObjectRepository::class);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->entityManager->isOpen()->willReturn(true);
        $this->entityManager
            ->getRepository(DeliveryImportQueue::class)
            ->willReturn($this->repository->reveal());

        $this->doctrine = $this->prophesize(ManagerRegistry::class);
        $this->doctrine->getManager()->willReturn($this->entityManager->reveal());

        $this->statusManager = $this->prophesize(DeliveryImportStatusManager::class);
        $this->statusManager->currentStatus(Argument::any())->willReturn(DeliveryImportQueue::STATUS_STARTED);
        $this->statusManager->requeued(Argument::cetera())->will(function () {});
        $this->statusManager->failed(Argument::cetera())->will(function () {});

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::cetera())->willReturn('Import interrupted');

        $this->subscriber = new DeliveryImportFailureSubscriber(
            $this->doctrine->reveal(),
            $this->statusManager->reveal(),
            $translator->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal()
        );
    }

    private function failedEvent($message, bool $willRetry): WorkerMessageFailedEvent
    {
        $event = new WorkerMessageFailedEvent(
            new Envelope($message),
            'async',
            new \RuntimeException('Deadlock detected')
        );

        if ($willRetry) {
            $event->setForRetry();
        }

        return $event;
    }

    public function testQueueIsMarkedAsFailedWhenTheMessageIsDropped()
    {
        $queue = new DeliveryImportQueue();
        $queue->setFilename('import.csv');
        $queue->setStatus(DeliveryImportQueue::STATUS_STARTED);

        $this->repository->findOneBy(['filename' => 'import.csv'])->willReturn($queue);

        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), false));

        $this->statusManager
            ->failed($queue, [['row' => 0, 'errors' => ['Import interrupted']]])
            ->shouldHaveBeenCalled();
    }

    public function testQueueIsPutBackInLineWhileTheMessageIsStillRetried()
    {
        $queue = new DeliveryImportQueue();
        $queue->setFilename('import.csv');
        $queue->setStatus(DeliveryImportQueue::STATUS_STARTED);

        $this->repository->findOneBy(Argument::cetera())->willReturn($queue);

        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), true));

        // The handler skips the imports that aren't pending, the retry would do nothing otherwise
        $this->statusManager->requeued($queue)->shouldHaveBeenCalled();
        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testCancelledQueueIsLeftAlone()
    {
        $queue = new DeliveryImportQueue();
        $queue->setFilename('import.csv');
        $queue->setStatus(DeliveryImportQueue::STATUS_STARTED);

        $this->repository->findOneBy(Argument::cetera())->willReturn($queue);
        $this->statusManager->currentStatus($queue)->willReturn(DeliveryImportQueue::STATUS_CANCELLED);

        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), false));
        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), true));

        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->statusManager->requeued(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * Saving the deliveries failed: the handler rolled them back, marked the import as
     * failed with the row it stopped on, and threw so that the message is not retried
     */
    public function testQueueFailedByTheHandlerIsLeftAlone()
    {
        $queue = new DeliveryImportQueue();
        $queue->setFilename('import.csv');
        $queue->setStatus(DeliveryImportQueue::STATUS_SAVING);

        $this->repository->findOneBy(Argument::cetera())->willReturn($queue);
        $this->statusManager->currentStatus($queue)->willReturn(DeliveryImportQueue::STATUS_FAILED);

        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), false));

        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testOtherMessagesAreIgnored()
    {
        $this->subscriber->onFailure($this->failedEvent(
            new ImportTasks('token', 'import.csv', new \DateTime()),
            false
        ));

        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testEntityManagerIsResetWhenItWasClosedByTheFailure()
    {
        $queue = new DeliveryImportQueue();
        $queue->setFilename('import.csv');
        $queue->setStatus(DeliveryImportQueue::STATUS_STARTED);

        $closed = $this->prophesize(EntityManagerInterface::class);
        $closed->isOpen()->willReturn(false);
        $this->doctrine->getManager()->willReturn($closed->reveal());
        $this->doctrine->resetManager()->willReturn($this->entityManager->reveal());

        $this->repository->findOneBy(['filename' => 'import.csv'])->willReturn($queue);

        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), false));

        // The queue is looked up on the manager that replaced the closed one
        $this->statusManager->failed($queue, Argument::any())->shouldHaveBeenCalled();
    }
}
