<?php

declare(strict_types=1);

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\ImportQueue;
use AppBundle\Message\CancelsImportedItems;
use AppBundle\Service\ImportStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * CancelImportedItemsHandler writes that the deliveries are cancelled before
 * their orders are, which happens once the message is handled. When that fails, the
 * message is retried, which the handler only goes through while they are "cancelling".
 */
class CancelImportedItemsFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly ImportStatusManager $statusManager,
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

        if (!$message instanceof CancelsImportedItems) {
            return;
        }

        // Whatever failed the message most likely failed a flush, which closes the entity manager
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->doctrine->getManager();
        if (!$entityManager->isOpen()) {
            $entityManager = $this->doctrine->resetManager();
        }

        /** @var ?ImportQueue $queue */
        $queue = $entityManager->find(ImportQueue::class, $message->getImportId());

        if (is_null($queue)) {
            return;
        }

        $this->logger->error(
            sprintf('Cancelling what import %s created failed%s', $queue->getFilename(), $event->willRetry() ? ', retrying' : ''),
            ['store_id' => $queue->getStore()?->getId(), 'error' => $event->getThrowable()->getMessage()]
        );

        // What was cancelled so far counts as such on the next attempt
        $this->statusManager->itemsCancellationFailed($queue, $event->willRetry());
    }
}
