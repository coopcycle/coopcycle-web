<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Vendor;
use AppBundle\Service\LoggingUtils;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

class OrderVendorProcessor implements OrderProcessorInterface
{
    private $entityManager;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        private LoggingUtils $loggingUtils
    )
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if (!$order->hasVendor()) {
            return;
        }

        if (!in_array($order->getState(), [ OrderInterface::STATE_CART ])) {
            return;
        }

        $restaurants = $this->getRestaurants($order);

        $this->processVendors($order, $restaurants);

        $vendor = $this->processVendor($order, $restaurants);
        $order->setVendor($vendor);
    }

    private function processVendors(OrderInterface $order, \SplObjectStorage $restaurants)
    {
        if (count($restaurants) === 0 && $order->isEmpty()) {
            $this->logger->debug(sprintf('Order %s | is empty, skipping',
                $this->loggingUtils->getOrderId($order)));
            return;
        }

        $this->logger->debug(sprintf('Order %s | Adding %d vendors to order',
            $this->loggingUtils->getOrderId($order), count($restaurants)));

        $originalVendors = new ArrayCollection();
        foreach ($order->getVendors() as $vendor) {
            $originalVendors->add($vendor);
        }

        $rest = ($order->getTotal() - $order->getFeeTotal());

        foreach ($restaurants as $restaurant) {

            $itemsTotal = $restaurants[$restaurant];
            $transferAmount = intval(ceil($rest * $order->getPercentageForRestaurant($restaurant)));

            $order->addRestaurant($restaurant, $itemsTotal, $transferAmount);
        }

        foreach ($originalVendors as $vendor) {
            // Make sure $vendor is already managed by Doctrine
            if ($this->entityManager->contains($vendor) && !$restaurants->contains($vendor->getRestaurant())) {
                $this->logger->debug(sprintf('Order %s | Removing vendor from order',
                    $this->loggingUtils->getOrderId($order)));
                $order->getVendors()->removeElement($vendor);
                $this->entityManager->remove($vendor);
            }
        }
    }

    private function processVendor(OrderInterface $order, \SplObjectStorage $restaurants): Vendor
    {
        $this->logger->debug(sprintf('Order %s | Checking if order needs vendor upgrade/downgrade',
            $this->loggingUtils->getOrderId($order)));

        $vendor = $order->getVendor();

        $this->logger->debug(sprintf('Order %s | There are %d vendors in order',
            $this->loggingUtils->getOrderId($order), count($restaurants)));

        if (count($restaurants) === 0) {

            return $vendor;
        }

        if (count($restaurants) === 1) {

            // Make sure the vendor matches

            // Do not use $restaurants->current()
            // It does not work

            foreach ($restaurants as $restaurant) {

                if ($vendor->getRestaurant() === $restaurant) {
                    $this->logger->debug(sprintf('Order %s | The vendor for order is OK, skipping',
                        $this->loggingUtils->getOrderId($order)));

                    return $vendor;
                }

                $this->logger->debug(sprintf('Order %s | The vendor for order is KO, fixing',
                    $this->loggingUtils->getOrderId($order)));

                return Vendor::withRestaurant($restaurant);
            }
        }

        //
        // Upgrade if needed
        //

        $hubs = new \SplObjectStorage();

        foreach ($restaurants as $restaurant) {
            if ($restaurant->belongsToHub()) {
                $hubs->attach($restaurant->getHub());
            }
        }

        if (count($hubs) === 1) {

            $hub = $hubs->current();

            if ($vendor->getHub() === $hub) {
                $this->logger->debug(sprintf('Order %s | The vendor for order is OK, skipping',
                    $this->loggingUtils->getOrderId($order)));

                return $vendor;
            }

            $this->logger->debug(sprintf('Order %s | The vendor for order is KO, fixing',
                $this->loggingUtils->getOrderId($order)));

            return Vendor::withHub($hub);
        }

        return $vendor;
    }

    private function getRestaurants(OrderInterface $order): \SplObjectStorage
    {
        $restaurants = new \SplObjectStorage();

        foreach ($order->getItems() as $item) {
            $restaurant = $item->getVariant()->getProduct()->getRestaurant();

            if (null === $restaurant) {
                continue;
            }

            if ($restaurants->contains($restaurant)) {
                $restaurants[$restaurant] += $item->getTotal();
            } else {
                $restaurants->attach($restaurant, $item->getTotal());
            }
        }

        return $restaurants;
    }
}
