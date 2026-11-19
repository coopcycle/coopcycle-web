<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\DeliveryImportStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Marks a delivery import as failed once it is given up on.
 *
 * ImportDeliveriesHandler only moves the queue out of "started" when it runs
 * to completion, so an error escaping it (a deadlock, a timeout, ...) used to
 * leave the import spinning forever in the admin, with no way to tell that it
 * had been dropped.
 */
class DeliveryImportFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
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

        // Whatever failed the message most likely failed a flush, which closes
        // the entity manager, so get a working one before writing the status
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->doctrine->getManager();
        if (!$entityManager->isOpen()) {
            $entityManager = $this->doctrine->resetManager();
        }

        $queue = $entityManager
            ->getRepository(DeliveryImportQueue::class)
            ->findOneBy(['filename' => $message->getFilename()]);

        if (null === $queue) {
            return;
        }

        // Already finished: cancelled by a dispatcher, or failed by the handler itself when
        // saving the deliveries failed. Read from the database, the queue in memory is the
        // one the worker loaded before.
        $unfinished = [
            DeliveryImportQueue::STATUS_PENDING,
            DeliveryImportQueue::STATUS_STARTED,
            DeliveryImportQueue::STATUS_SAVING,
        ];
        if (!in_array($this->statusManager->currentStatus($queue), $unfinished, true)) {
            return;
        }

        // Another attempt may still succeed. The handler only imports a pending
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

        // The deliveries are saved in a single transaction, rolled back by the handler
        // when it fails: an error that gets here came before, and nothing was imported.
        $this->statusManager->failed($queue, DeliveryImportStatusManager::errorForWholeImport(
            $this->translator->trans('deliveries.imports.error.interrupted')
        ));
    }
}
