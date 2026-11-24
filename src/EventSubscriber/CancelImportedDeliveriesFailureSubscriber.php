<?php

declare(strict_types=1);

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Message\CancelImportedDeliveries;
use AppBundle\Service\DeliveryImportStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * CancelImportedDeliveriesHandler writes that the deliveries are cancelled before
 * their orders are, which happens once the message is handled. When that fails, the
 * message is retried, which the handler only goes through while they are "cancelling".
 */
class CancelImportedDeliveriesFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly DeliveryImportStatusManager $statusManager,
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

        if (!$message instanceof CancelImportedDeliveries) {
            return;
        }

        // Whatever failed the message most likely failed a flush, which closes the entity manager
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->doctrine->getManager();
        if (!$entityManager->isOpen()) {
            $entityManager = $this->doctrine->resetManager();
        }

        /** @var ?DeliveryImportQueue $queue */
        $queue = $entityManager->find(DeliveryImportQueue::class, $message->getImportId());

        if (is_null($queue)) {
            return;
        }

        $this->logger->error(
            sprintf('Cancelling the deliveries of import %s failed%s', $queue->getFilename(), $event->willRetry() ? ', retrying' : ''),
            ['store_id' => $queue->getStore()->getId(), 'error' => $event->getThrowable()->getMessage()]
        );

        // The deliveries cancelled so far count as such on the next attempt
        $this->statusManager->deliveriesCancellationFailed($queue, $event->willRetry());
    }
}
