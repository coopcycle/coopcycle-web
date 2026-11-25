<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\DeliveryImportStatusManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Marks a delivery import as failed once it is given up on.
 *
 * DeliveryImporter only moves the queue out of "started" when it runs
 * to completion, so an error escaping it (a deadlock, a timeout, ...) used to
 * leave the import spinning forever in the admin, with no way to tell that it
 * had been dropped.
 */
class DeliveryImportFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly DeliveryImportStatusManager $statusManager,
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

        if (!$message instanceof ImportDeliveries) {
            return;
        }

        $queue = $this->statusManager->findUnfinished($message->getFilename());
        if (null === $queue) {
            return;
        }

        // Another attempt may still succeed. DeliveryImporter only imports a pending
        // file, to not import it twice when the transport redelivers it, so the
        // import has to be put back in line for the retry to go through.
        if ($event->willRetry()) {
            $this->statusManager->requeued($queue);
            return;
        }

        $this->logger->error(
            sprintf('Marking delivery import %s as failed', $message->getFilename()),
            ['error' => $event->getThrowable()->getMessage()]
        );

        // The deliveries are saved in a single transaction, rolled back by DeliveryImporter
        // when it fails: an error that gets here came before, and nothing was imported.
        $this->statusManager->failed($queue, DeliveryImportStatusManager::errorForWholeImport(
            $this->translator->trans('deliveries.imports.error.interrupted')
        ));
    }
}
