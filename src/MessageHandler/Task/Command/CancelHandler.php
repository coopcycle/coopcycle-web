<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\Command\Cancel as CommandCancel;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
class CancelHandler
{
    public function __construct(
        private readonly MessageBusInterface $eventBus,
        private readonly OrderManager $orderManager,
        private readonly PricingManager $pricingManager,
        private readonly LoggerInterface $logger
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
        // Track orders that need price recalculation to avoid processing the same order multiple times
        $ordersToRecalculate = [];

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
            } elseif (!$cancelOrder && $order->getState() !== OrderInterface::STATE_CANCELLED && $order->getState() !== OrderInterface::STATE_REFUSED) {
                // For non-cancelled orders with cancelled tasks, mark for price recalculation
                $ordersToRecalculate[$order->getId()] = ['order' => $order, 'delivery' => $delivery];
            }
        }

        // Recalculate prices for all affected orders (outside the loop to avoid recalculating price of the same order multiple times)
        foreach ($ordersToRecalculate as $data) {
            $this->recalculatePriceIfNeeded($data['order'], $data['delivery']);
        }
    }

    /**
     * Recalculate order price after task cancellation, preserving arbitrary prices and manual supplements
     */
    private function recalculatePriceIfNeeded(OrderInterface $order, Delivery $delivery): void
    {
        if ($order->isFoodtech()) {
            $this->logger->info('Skipping price recalculation for foodtech order', ['order' => $order->getId()]);
            return;
        }

        $deliveryPrice = $order->getDeliveryPrice();
        if ($deliveryPrice instanceof ArbitraryPrice) {
            $this->logger->info('Keeping arbitrary price after task cancellation', ['order' => $order->getId()]);
            return;
        }

        $oldTotal = $order->getTotal();

        $existingManualSupplements = $order->getManualSupplements();

        $productVariants = $this->pricingManager->getProductVariantsWithPricingStrategy(
            $delivery,
            new CalculateUsingPricingRules($existingManualSupplements)
        );

        $this->pricingManager->processDeliveryOrder($order, $productVariants);

        $this->logger->info('Recalculated price after task cancellation', [
            'order' => $order->getId(),
            'oldTotal' => $oldTotal,
            'newTotal' => $order->getTotal(),
        ]);
    }
}
