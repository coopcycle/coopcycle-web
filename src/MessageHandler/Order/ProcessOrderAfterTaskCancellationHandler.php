<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderPriceUpdated;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Message\Order\ProcessOrderAfterTaskCancellation;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler()]
class ProcessOrderAfterTaskCancellationHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly MessageBusInterface $eventBus,
        private readonly DeliveryManager $deliveryManager,
        private readonly PricingManager $pricingManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly LoggerInterface $feeCalculationLogger
    )
    {}

    public function __invoke(ProcessOrderAfterTaskCancellation $message)
    {
        $order = $this->orderRepository->find($message->getOrderId());
        if (null === $order) {
            $this->logger->warning('Order not found for processing after task cancellation', [
                'orderId' => $message->getOrderId()
            ]);
            return;
        }

        $delivery = $order->getDelivery();
        if (null === $delivery) {
            $this->logger->warning('Delivery not found for processing after task cancellation', [
                'orderId' => $message->getOrderId()
            ]);
            return;
        }

        $this->processOrderIfNeeded($order, $delivery, $message->shouldRecalculatePrice());
    }

    private function processOrderIfNeeded($order, Delivery $delivery, bool $recalculatePrice): void
    {
        if ($order->isFoodtech()) {
            // It's not possible to cancel tasks for foodtech orders only an entire order can be cancelled
            // so the logic that follows does not apply
            $this->logger->info('Skipping processing for foodtech order', ['order' => $order->getId()]);
            return;
        }

        $this->deliveryManager->calculateRoute($delivery);

        if ($recalculatePrice) {
            $this->recalculatePriceIfNeeded($order, $delivery);
        }
    }

    /**
     * Recalculate order price after task cancellation, preserving arbitrary prices and manual supplements
     */
    private function recalculatePriceIfNeeded($order, Delivery $delivery): void
    {
        try {
            $deliveryPrice = $order->getDeliveryPrice();
        } catch (\Exception $e) {
            $this->feeCalculationLogger->warning('Failed to get delivery price', ['order' => $order->getId()]);
            return;
        }

        if ($deliveryPrice instanceof ArbitraryPrice) {
            $this->feeCalculationLogger->info('Keeping arbitrary price after task cancellation', ['order' => $order->getId()]);
            return;
        }

        if (null === $delivery->getStore()) {
            $this->feeCalculationLogger->info('Skipping price recalculation for order without a Store', ['order' => $order->getId()]);
            return;
        }

        $oldTotal = $order->getTotal();
        $oldTaxTotal = $order->getTaxTotal();

        $existingManualSupplements = $order->getManualSupplements();

        $productVariants = $this->pricingManager->getProductVariantsWithPricingStrategy(
            $delivery,
            new CalculateUsingPricingRules($existingManualSupplements)
        );

        $this->pricingManager->processDeliveryOrder($order, $productVariants);

        $event = new OrderPriceUpdated($order,
            $order->getTotal(),
            $order->getTaxTotal(),
            $oldTotal,
            $oldTaxTotal
        );
        $this->eventBus->dispatch($event);

        $this->entityManager->flush();

        $this->feeCalculationLogger->info('Recalculated price after task cancellation', [
            'order' => $order->getId(),
            'oldTotal' => $oldTotal,
            'newTotal' => $order->getTotal(),
        ]);
    }
}
