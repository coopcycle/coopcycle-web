<?php

namespace AppBundle\Pricing;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event\OrderPriceRecalculated;
use AppBundle\Entity\Delivery;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;

class PricingManager
{
    public function __construct(
        private DeliveryManager        $deliveryManager,
        private OrderManager           $orderManager,
        private OrderFactory           $orderFactory,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
        private MessageBus             $eventBus,
        private IriConverterInterface  $iriConverter
    )
    {}

    /**
     * @return OrderInterface|null
     */
    public function createOrder(Delivery $delivery): ?OrderInterface
    {
        $store = $delivery->getStore();

        if (null !== $store && $store->getCreateOrders()) {

            $price = $this->deliveryManager->getPrice($delivery, $store->getPricingRuleSet());

            if (null === $price) {
                $this->logger->error('Price could not be calculated');

                return null;
            }

            $price = (int) $price;

            $order = $this->orderFactory->createForDelivery($delivery, $price);

            // We need to persist the order first,
            // because an auto increment is needed to generate a number
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            $this->orderManager->onDemand($order);

            $this->entityManager->flush();

            return $order;
        }

        return null;
    }

    public function updateOrder(Delivery $delivery, ?object $triggered_by = null): ?OrderInterface
    {
        /** @var ?OrderInterface $order */
        $order = $delivery->getOrder();
        if (is_null($order)) {
            $this->logger->info("No order set to this delivery, skipping");
            return null;
        }

        if ($order->isPaid()) {
            $this->logger->info("Order is already payed, skipping");
            return null;
        }

        $store = $delivery->getStore();
        if (is_null($store)) {
            $this->logger->info("No store set to this delivery, skipping");
            return null;
        }

        if ($order->getItems()->count() > 1) {
            $this->logger->info("Order has more than one item, skipping");
            return null;
        }

        $delivery->setDistance(ceil($this->deliveryManager->calculateDistance(
            $delivery,
            $delivery->getTasks('not task.isCancelled()')
        )));

        if ($order->getItems()->first()->isImmutable()) {
            $this->logger->info("Order item is immutable, skipping");
            return null;
        }

        $old_price = $order->getItems()->first()->getUnitPrice();

        $price = $this->deliveryManager->getPrice($delivery, $store->getPricingRuleSet());
        if (is_null($price)) {
            $this->logger->error('Price could not be calculated');
            return null;
        }

        // Early exit if price didn't change
        if ($old_price === $price) {
            $this->logger->info("Price didn't change, skipping");
            return $order;
        }

        $order->getItems()->map(function ($item) use ($price) {
            $item->setUnitPrice((int)$price);
        });

        $order->recalculateItemsTotal();
        $order->recalculateAdjustmentsTotal();

        // If everything is fine, remove the payment
        // TODO: See if other behavior is needed
        // TODO: Move payments logic changes to a listener of OrderPriceRecalculated
        $order->getPayments()->map(function ($payment) use (&$order) {
            $order->removePayment($payment);
        });

        $this->entityManager->persist($order);

        $this->eventBus->handle(new OrderPriceRecalculated(
                $order,
                $price,
                $old_price,
                $this->iriConverter->getIriFromItem($triggered_by),
            )
        );

        $this->entityManager->flush();
        return $order;
    }
}
