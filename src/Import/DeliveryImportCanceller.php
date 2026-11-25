<?php

declare(strict_types=1);

namespace AppBundle\Import;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Task;
use AppBundle\Service\OrderManager;
use AppBundle\Service\TaskManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Cancels the deliveries a file import created, with their orders. Those already
 * under way, or with an order that can't be cancelled any more, are left as is.
 */
class DeliveryImportCanceller implements ImportCancellerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskManager $taskManager,
        private readonly OrderManager $orderManager,
        private readonly StateMachineFactoryInterface $stateMachineFactory,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function getType(): string
    {
        return ImportQueue::TYPE_DELIVERIES;
    }

    public function findIds(ImportQueue $queue): array
    {
        /** @var DeliveryRepository $repository */
        $repository = $this->entityManager->getRepository(Delivery::class);

        return $repository->findIdsByImport($queue->getId(), $queue->getStore()?->getId());
    }

    public function cancel(int $id, ImportQueue $queue): bool
    {
        /** @var ?Delivery $delivery */
        $delivery = $this->entityManager->find(Delivery::class, $id);

        if (is_null($delivery)) {
            return false;
        }

        $context = ['import_id' => $queue->getId(), 'store_id' => $queue->getStore()?->getId()];

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
                    $context
                );
                return false;
            }
        }

        // As checked by CancelTasksProcessor: cancelling all its tasks cancels the order too
        if (!is_null($order) && !$this->isCancelled($order) && !$this->canCancel($order)) {
            $this->logger->info(
                sprintf('Leaving delivery #%d as is, its order %s is %s', $delivery->getId(), $order->getNumber() ?? $order->getId(), $order->getState()),
                $context
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
                $context
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
