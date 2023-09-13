<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class PricingManager
{
    public function __construct(
        private DeliveryManager $deliveryManager,
        private OrderManager $orderManager,
        private OrderFactory $orderFactory,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger)
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
}
