<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\Command\Cancel as CommandCancel;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
class CancelHandler
{
    public function __construct(
        private readonly MessageBusInterface $eventBus,
        private readonly OrderManager $orderManager
    )
    {}

    public function __invoke(CommandCancel $command)
    {
        // TODO Reorder linked tasks?

        foreach ($command->getTasks() as $task) {
            $event = new Event\TaskCancelled($task);
            $this->eventBus->dispatch(
                (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
            );

            $task->setStatus(Task::STATUS_CANCELLED);
        }

        $this->handleStateChangesForTasks($command->getTasks());
    }

    /**
     * @param Task[] $cancelledTasks
     */
    private function handleStateChangesForTasks(array $cancelledTasks): void
    {
        foreach ($cancelledTasks as $cancelledTask) {

            $delivery = $cancelledTask->getDelivery();
            if (null === $delivery) {
                continue;
            }

            $order = $delivery->getOrder();
            if (null === $order) {
                continue;
            }

            // if all tasks of a delivery are cancelled, cancel the linked order
            $tasks = $delivery->getTasks();
            $cancelOrder = true;
            foreach ($tasks as $task) {
                if ($task->getId() !== $cancelledTask->getId() && $task->getStatus() !== Task::STATUS_CANCELLED) {
                    $cancelOrder = false;
                    break;
                }
            }

            // do not cancel order if order is "refused"
            if ($cancelOrder && $order->getState() !== OrderInterface::STATE_CANCELLED && $order->getState() !== OrderInterface::STATE_REFUSED) {
                $this->eventBus->dispatch(new TaskUpdated($cancelledTask));
                $this->orderManager->cancel($order, 'All tasks were cancelled');
            }
        }
    }
}
