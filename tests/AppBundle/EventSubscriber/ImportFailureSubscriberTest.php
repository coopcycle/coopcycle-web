<?php

namespace Tests\AppBundle\EventSubscriber;

use AppBundle\Entity\ImportQueue;
use AppBundle\EventSubscriber\ImportFailureSubscriber;
use AppBundle\Import\DeliveryImportHandler;
use AppBundle\Import\ImportRegistry;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Message\ImportTasks;
use AppBundle\Message\ImportTasksFromFile;
use AppBundle\Service\ImportStatusManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportFailureSubscriberTest extends TestCase
{
    use ProphecyTrait;

    private $statusManager;
    private ImportFailureSubscriber $subscriber;

    public function setUp(): void
    {
        $this->statusManager = $this->prophesize(ImportStatusManager::class);
        $this->statusManager->findUnfinished(Argument::cetera())->willReturn(null);
        $this->statusManager->requeued(Argument::cetera())->will(function () {});
        $this->statusManager->failed(Argument::cetera())->will(function () {});

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::cetera())->willReturn('Import interrupted');

        // What the import is tells the dispatcher what went wrong with it
        $importHandler = $this->prophesize(DeliveryImportHandler::class);
        $importHandler->errorKey(Argument::any())->willReturn('deliveries.imports.error.interrupted');

        $registry = new ImportRegistry(
            new ServiceLocator([ImportQueue::TYPE_DELIVERIES => fn () => $importHandler->reveal()]),
            new ServiceLocator([])
        );

        $this->subscriber = new ImportFailureSubscriber(
            $registry,
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

    private function unfinishedImport(): ImportQueue
    {
        $queue = new ImportQueue(ImportQueue::TYPE_DELIVERIES);
        $queue->setFilename('import.csv');
        $queue->setStatus(ImportQueue::STATUS_STARTED);

        $this->statusManager->findUnfinished('import.csv', Argument::any())->willReturn($queue);

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

        // The runner skips the imports that aren't pending, the retry would do nothing otherwise
        $this->statusManager->requeued($queue)->shouldHaveBeenCalled();
        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * Cancelled by a dispatcher, or failed by the runner itself when saving what it read failed
     */
    public function testFinishedQueueIsLeftAlone()
    {
        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), false));
        $this->subscriber->onFailure($this->failedEvent(new ImportDeliveries('import.csv'), true));

        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->statusManager->requeued(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * A file of tasks is run by the same worker, and gives up the same way
     */
    public function testATaskImportIsMarkedAsFailedToo()
    {
        $queue = $this->unfinishedImport();

        $this->subscriber->onFailure($this->failedEvent(new ImportTasksFromFile('import.csv'), false));

        $this->statusManager
            ->failed($queue, [['row' => 0, 'errors' => ['Import interrupted']]])
            ->shouldHaveBeenCalled();
    }

    /**
     * The legacy task import, which is not run by this worker at all
     */
    public function testOtherMessagesAreIgnored()
    {
        $this->subscriber->onFailure($this->failedEvent(
            new ImportTasks('token', 'import.csv', new \DateTime()),
            false
        ));

        $this->statusManager->failed(Argument::cetera())->shouldNotHaveBeenCalled();
    }
}
