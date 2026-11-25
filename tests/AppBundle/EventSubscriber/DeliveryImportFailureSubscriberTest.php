<?php

namespace Tests\AppBundle\EventSubscriber;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\EventSubscriber\DeliveryImportFailureSubscriber;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Message\ImportTasks;
use AppBundle\Service\DeliveryImportStatusManager;
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

    private $statusManager;
    private DeliveryImportFailureSubscriber $subscriber;

    public function setUp(): void
    {
        $this->statusManager = $this->prophesize(DeliveryImportStatusManager::class);
        $this->statusManager->findUnfinished(Argument::any())->willReturn(null);
        $this->statusManager->requeued(Argument::cetera())->will(function () {});
        $this->statusManager->failed(Argument::cetera())->will(function () {});

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::cetera())->willReturn('Import interrupted');

        $this->subscriber = new DeliveryImportFailureSubscriber(
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

    private function unfinishedImport(): DeliveryImportQueue
    {
        $queue = new DeliveryImportQueue();
        $queue->setFilename('import.csv');
        $queue->setStatus(DeliveryImportQueue::STATUS_STARTED);

        $this->statusManager->findUnfinished('import.csv')->willReturn($queue);

        return $queue;
    }

    public function testQueueIsMarkedAsFailedWhenTheMessageIsDropped()
    {
        $queue = $this->unfinishedImport();

        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), false));

        $this->statusManager
            ->failed($queue, [['row' => 0, 'errors' => ['Import interrupted']]])
            ->shouldHaveBeenCalled();
    }

    public function testQueueIsPutBackInLineWhileTheMessageIsStillRetried()
    {
        $queue = $this->unfinishedImport();

        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), true));

        // The handler skips the imports that aren't pending, the retry would do nothing otherwise
        $this->statusManager->requeued($queue)->shouldHaveBeenCalled();
        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * Cancelled by a dispatcher, or failed by DeliveryImporter itself when saving the deliveries failed
     */
    public function testFinishedQueueIsLeftAlone()
    {
        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), false));
        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), true));

        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->statusManager->requeued(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testOtherMessagesAreIgnored()
    {
        $this->subscriber->onFailure($this->failedEvent(
            new ImportTasks('token', 'import.csv', new \DateTime()),
            false
        ));

        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }
}
