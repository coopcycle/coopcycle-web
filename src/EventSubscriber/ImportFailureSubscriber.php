<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Import\ImportHandlerInterface;
use AppBundle\Import\ImportRegistry;
use AppBundle\Message\RunsImport;
use AppBundle\Service\ImportStatusManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Marks an import as failed once it is given up on.
 *
 * ImportRunner only moves the queue out of "started" when it runs
 * to completion, so an error escaping it (a deadlock, a timeout, ...) used to
 * leave the import spinning forever in the admin, with no way to tell that it
 * had been dropped.
 */
class ImportFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportStatusManager $statusManager,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onFailure',
        ];
    }

    public function onFailure(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof RunsImport) {
            return;
        }

        // Already finished: cancelled by a dispatcher, or failed by the runner itself
        // when saving what the file held failed
        $queue = $this->statusManager->findUnfinished($message->getFilename(), $message->getQueueId());
        if (null === $queue) {
            return;
        }

        // Another attempt may still succeed. ImportRunner only imports a pending
        // file, to not import it twice when the transport redelivers it, so the
        // import has to be put back in line for the retry to go through.
        if ($event->willRetry()) {
            $this->statusManager->requeued($queue);
            return;
        }

        $this->logger->error(
            sprintf('Marking import %s as failed', $message->getFilename()),
            ['import_id' => $queue->getId(), 'import_type' => $queue->getType(), 'error' => $event->getThrowable()->getMessage()]
        );

        // A file is saved in a single transaction, rolled back by the runner when it
        // fails: an error that gets here came before, and nothing was imported.
        $errorKey = $this->registry->handlerFor($queue->getType())
            ->errorKey(ImportHandlerInterface::ERROR_INTERRUPTED);

        $this->statusManager->failed($queue, ImportStatusManager::errorForWholeImport(
            $this->translator->trans($errorKey)
        ));
    }
}
