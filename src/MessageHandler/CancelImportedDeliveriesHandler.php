<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Task;
use AppBundle\Message\CancelImportedDeliveries;
use AppBundle\Service\DeliveryImportStatusManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\TaskManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Cancels the deliveries a file import created, with their orders. Those already
 * under way, or with an order that can't be cancelled any more, are left as is.
 *
 * It can be run again on the same import: the deliveries already cancelled count as such.
 */
#[AsMessageHandler]
class CancelImportedDeliveriesHandler
{
    /**
     * Flushed, and the progress reported, every few deliveries: there can be more than a thousand.
     * Their orders are cancelled once the message is handled, failing it if that fails:
     * see CancelImportedDeliveriesFailureSubscriber.
     */
    const BATCH_SIZE = 25;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeliveryImportStatusManager $statusManager,
        private TaskManager $taskManager,
        private OrderManager $orderManager,
        private StateMachineFactoryInterface $stateMachineFactory,
        private LoggerInterface $logger,
    )
    {
    }

    public function __invoke(CancelImportedDeliveries $message): void
    {
        /** @var ?DeliveryImportQueue $queue */
        $queue = $this->entityManager->find(DeliveryImportQueue::class, $message->getImportId());

        if (is_null($queue)) {
            $this->logger->error(sprintf('Could not find delivery import #%d to cancel its deliveries', $message->getImportId()));
            return;
        }

        $storeId = $queue->getStore()->getId();

        // A message handed out again once it's done
        if (DeliveryImportQueue::DELIVERIES_CANCELLING !== $queue->getDeliveriesCancellation()) {
            $this->logger->info(
                sprintf('Not cancelling the deliveries of import %s, they are %s', $queue->getFilename(), $queue->getDeliveriesCancellation() ?? 'not to be cancelled'),
                ['store_id' => $storeId]
            );
            return;
        }

        /** @var DeliveryRepository $repository */
        $repository = $this->entityManager->getRepository(Delivery::class);
        $ids = $repository->findIdsByImport($queue->getId(), $storeId);

        $toCancel = count($ids);
        $cancelled = 0;
        $skipped = 0;

        $this->statusManager->deliveriesCancelProgress($queue, $toCancel, $cancelled, $skipped);

        foreach (array_chunk($ids, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $id) {
                /** @var ?Delivery $delivery */
                $delivery = $this->entityManager->find(Delivery::class, $id);

                if (!is_null($delivery) && $this->cancel($delivery, $storeId)) {
                    $cancelled++;
                } else {
                    $skipped++;
                }
            }

            // Not cleared: in a worker, the events cancelling the orders are only handled
            // once the whole message is (see DispatchAfterCurrentBusStamp), and need them
            $this->entityManager->flush();

            $this->statusManager->deliveriesCancelProgress($queue, $toCancel, $cancelled, $skipped);
        }

        $this->statusManager->deliveriesCancelled($queue, $toCancel, $cancelled, $skipped);

        $this->logger->info(
            sprintf('Cancelled %d of the %d deliveries of import %s, %d left as is', $cancelled, $toCancel, $queue->getFilename(), $skipped),
            ['store_id' => $storeId]
        );
    }

    /**
     * @return bool whether the delivery is cancelled, now or already
     */
    private function cancel(Delivery $delivery, int $storeId): bool
    {
        $tasks = array_values($delivery->getTasks('not task.isCancelled()'));
        $order = $delivery->getOrder();

        if (empty($tasks)) {
            // A previous attempt failed once the tasks were cancelled, before the order was
            if (!is_null($order) && !$this->isCancelled($order) && $this->canCancel($order)) {
                $this->orderManager->cancel($order, 'All tasks were cancelled');
            }

            // Or cancelled since by a dispatcher
            return true;
        }

        // Assigned tasks are cancelled too, as from the dispatch board: only those started are left
        foreach ($tasks as $task) {
            if (Task::STATUS_TODO !== $task->getStatus()) {
                $this->logger->info(
                    sprintf('Leaving delivery #%d as is, its task #%d is %s', $delivery->getId(), $task->getId(), $task->getStatus()),
                    ['store_id' => $storeId]
                );
                return false;
            }
        }

        // As checked by CancelTasksProcessor: cancelling all its tasks cancels the order too
        if (!is_null($order) && !$this->isCancelled($order) && !$this->canCancel($order)) {
            $this->logger->info(
                sprintf('Leaving delivery #%d as is, its order %s is %s', $delivery->getId(), $order->getNumber() ?? $order->getId(), $order->getState()),
                ['store_id' => $storeId]
            );
            return false;
        }

        try {
            // All of them in a single command: the order is cancelled once, without a price to recalculate
            $this->taskManager->cancelTasks($tasks);
        } catch (HandlerFailedException $e) {
            // Not to be flushed with the next deliveries
            foreach ($tasks as $task) {
                $this->entityManager->refresh($task);
            }

            $this->logger->warning(
                sprintf('Could not cancel delivery #%d: %s', $delivery->getId(), $e->getMessage()),
                ['store_id' => $storeId]
            );
            return false;
        }

        return true;
    }

    private function isCancelled(OrderInterface $order): bool
    {
        return in_array($order->getState(), [OrderInterface::STATE_CANCELLED, OrderInterface::STATE_REFUSED], true);
    }

    private function canCancel(OrderInterface $order): bool
    {
        return $this->stateMachineFactory->get($order, OrderTransitions::GRAPH)->can(OrderTransitions::TRANSITION_CANCEL);
    }
}
