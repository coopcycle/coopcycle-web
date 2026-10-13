<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\LiveUpdates;
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
        private readonly LiveUpdates $liveUpdates,
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
        // Another attempt may still succeed, leave the import as it is
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof ImportDeliveries) {
            return;
        }

        $this->logger->error(
            sprintf('Marking delivery import %s as failed', $message->getFilename()),
            ['error' => $event->getThrowable()->getMessage()]
        );

        // Whatever failed the message most likely failed a flush, which closes
        // the entity manager, so get a working one before writing the status
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

        $queue->setStatus(DeliveryImportQueue::STATUS_FAILED);
        $queue->setFinishedAt(new \DateTime());

        // The handler flushes as it goes, so the rows handled before the error
        // are already imported: say so rather than let a dispatcher assume the
        // file has to be imported again as a whole.
        $queue->setErrors([
            [
                'row' => 0,
                'errors' => [
                    $this->translator->trans('deliveries.imports.error.interrupted'),
                ],
            ],
        ]);

        $entityManager->flush();

        $this->liveUpdates->toDispatchers('delivery_import:updated', [
            'filename' => $queue->getFilename(),
            'status' => DeliveryImportQueue::STATUS_FAILED,
        ]);
    }
}
