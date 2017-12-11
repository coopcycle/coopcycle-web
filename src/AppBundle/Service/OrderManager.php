<?php

namespace AppBundle\Service;

use AppBundle\Entity\Order;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use AppBundle\Service\PaymentService;
use Predis\Client as Redis;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class OrderManager
{
    private $deliveryServiceFactory;
    private $payment;
    private $redis;
    private $serializer;
    private $taxRateResolver;
    private $calculator;
    private $taxCategoryRepository;
    private $deliveryManager;

    public function __construct(PaymentService $payment, DeliveryServiceFactory $deliveryServiceFactory,
        Redis $redis, SerializerInterface $serializer,
        TaxRateResolverInterface $taxRateResolver, CalculatorInterface $calculator,
        TaxCategoryRepositoryInterface $taxCategoryRepository, DeliveryManager $deliveryManager)
    {
        $this->deliveryServiceFactory = $deliveryServiceFactory;
        $this->payment = $payment;
        $this->redis = $redis;
        $this->serializer = $serializer;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->deliveryManager = $deliveryManager;
    }

    public function pay(Order $order, $stripeToken)
    {
        $this->payment->authorize($order, $stripeToken);

        $order->setStatus(Order::STATUS_WAITING);

        $channel = sprintf('restaurant:%d:orders', $order->getRestaurant()->getId());
        $this->redis->publish($channel, $this->serializer->serialize($order, 'jsonld'));
    }

    public function accept(Order $order)
    {
        // Order MUST have status = WAITING
        if ($order->getStatus() !== Order::STATUS_WAITING) {
            throw new \Exception(sprintf('Order #%d cannot be accepted anymore', $order->getId()));
        }

        $this->payment->capture($order);

        $order->setStatus(Order::STATUS_ACCEPTED);

        $this->deliveryServiceFactory
            ->createForRestaurant($order->getRestaurant())
            ->create($order);
    }

    public function applyTaxes(Order $order)
    {
        $orderTotalTax = 0;
        $orderTotalIncludingTax = 0;

        foreach ($order->getOrderedItem() as $item) {
            $rate = $this->taxRateResolver->resolve($item->getMenuItem());

            if (null === $rate) {
                continue;
            }

            $itemTotalIncludingTax = $item->getPrice() * $item->getQuantity();

            $orderTotalTax += $this->calculator->calculate($itemTotalIncludingTax, $rate);
            $orderTotalIncludingTax += $itemTotalIncludingTax;
        }

        $order->setTotalExcludingTax($orderTotalIncludingTax - $orderTotalTax);
        $order->setTotalTax($orderTotalTax);
        $order->setTotalIncludingTax($orderTotalIncludingTax);

        // Also apply taxes on delivery
        $this->deliveryManager->applyTaxes($order->getDelivery());
    }
}
