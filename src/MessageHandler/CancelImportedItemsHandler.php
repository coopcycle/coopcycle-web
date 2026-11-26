<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\ImportQueue;
use AppBundle\Import\ImportRegistry;
use AppBundle\Message\CancelsImportedItems;
use AppBundle\Service\ImportStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Cancels what a file import created. What is already under way, or too far gone to be
 * cancelled, is left as is — which of the two it is depends on the kind of import, and
 * is the business of the ImportCancellerInterface its type names.
 *
 * It can be run again on the same import: what is already cancelled counts as such.
 */
#[AsMessageHandler]
class CancelImportedItemsHandler
{
    /**
     * Flushed, and the progress reported, every few items: there can be more than a thousand.
     * The orders of imported deliveries are cancelled once the message is handled, failing it
     * if that fails: see CancelImportedItemsFailureSubscriber.
     */
    const BATCH_SIZE = 25;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImportRegistry $registry,
        private ImportStatusManager $statusManager,
        private LoggerInterface $logger,
    )
    {
    }

    public function __invoke(CancelsImportedItems $message): void
    {
        /** @var ?ImportQueue $queue */
        $queue = $this->entityManager->find(ImportQueue::class, $message->getImportId());

        if (is_null($queue)) {
            $this->logger->error(sprintf('Could not find import #%d to cancel what it created', $message->getImportId()));
            return;
        }

        $context = ['import_id' => $queue->getId(), 'import_type' => $queue->getType()];

        // A message handed out again once it's done
        if (ImportQueue::ITEMS_CANCELLING !== $queue->getItemsCancellation()) {
            $this->logger->info(
                sprintf('Not cancelling what import %s created, it is %s', $queue->getFilename(), $queue->getItemsCancellation() ?? 'not to be cancelled'),
                $context
            );
            return;
        }

        $canceller = $this->registry->cancellerFor($queue->getType());

        $ids = $canceller->findIds($queue);

        $toCancel = count($ids);
        $cancelled = 0;
        $skipped = 0;

        $this->statusManager->itemsCancelProgress($queue, $toCancel, $cancelled, $skipped);

        foreach (array_chunk($ids, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $id) {
                if ($canceller->cancel($id, $queue)) {
                    $cancelled++;
                } else {
                    $skipped++;
                }
            }

            // Not cleared: in a worker, the events cancelling the orders are only handled
            // once the whole message is (see DispatchAfterCurrentBusStamp), and need them
            $this->entityManager->flush();

            $this->statusManager->itemsCancelProgress($queue, $toCancel, $cancelled, $skipped);
        }

        $this->statusManager->itemsCancelled($queue, $toCancel, $cancelled, $skipped);

        $this->logger->info(
            sprintf('Cancelled %d of the %d things import %s created, %d left as is', $cancelled, $toCancel, $queue->getFilename(), $skipped),
            $context
        );
    }
}
