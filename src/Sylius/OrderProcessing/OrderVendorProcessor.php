<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class OrderVendorProcessor implements OrderProcessorInterface
{
    private $entityManager;
    private $adjustmentFactory;
    private $translator;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        AdjustmentFactoryInterface $adjustmentFactory,
        TranslatorInterface $translator,
        LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->adjustmentFactory = $adjustmentFactory;
        $this->translator = $translator;
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

        $order->removeAdjustments(AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT);

        $restaurants = $this->getRestaurants($order);

        $this->processVendors($order, $restaurants);

        $vendor = $this->processVendor($order, $restaurants);
        $order->setVendor($vendor);

        if ($order->isMultiVendor()) {
            $this->processTransferAmountAdjustments($order);
        }
    }

    private function processVendors(OrderInterface $order, \SplObjectStorage $restaurants)
    {
        $this->logger->debug(sprintf('Adding %d vendors to order #%d', count($restaurants), $order->getId()));

        $originalVendors = new ArrayCollection();
        foreach ($order->getVendors() as $vendor) {
            $originalVendors->add($vendor);
        }

        $rest = ($order->getTotal() - $order->getFeeTotal());

        foreach ($restaurants as $restaurant) {

            $itemsTotal = $restaurants[$restaurant];
            $transferAmount = ($rest * $order->getPercentageForRestaurant($restaurant));

            $order->addRestaurant($restaurant, $itemsTotal, $transferAmount);
        }

        foreach ($originalVendors as $vendor) {
            if (!$restaurants->contains($vendor->getRestaurant())) {
                $order->getVendors()->removeElement($vendor);
                $this->entityManager->remove($vendor);
            }
        }
    }

    private function processVendor(OrderInterface $order, \SplObjectStorage $restaurants): Vendor
    {
        $this->logger->debug(sprintf('Checking if order #%d needs vendor upgrade/downgrade', $order->getId()));

        $vendor = $order->getVendor();

        $this->logger->debug(sprintf('There are %d vendors in order #%d', count($restaurants), $order->getId()));

        if (count($restaurants) === 0) {

            return $vendor;
        }

        if (count($restaurants) === 1) {

            // Make sure the vendor matches

            // Do not use $restaurants->current()
            // It does not work

            foreach ($restaurants as $restaurant) {

                if ($vendor->getRestaurant() === $restaurant) {
                    $this->logger->debug(sprintf('The vendor for order %d is OK, skipping', $order->getId()));

                    return $vendor;
                }

                $this->logger->debug(sprintf('The vendor for order %d is KO, fixing', $order->getId()));

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
                $this->logger->debug(sprintf('The vendor for order %d is OK, skipping', $order->getId()));

                return $vendor;
            }

            $this->logger->debug(sprintf('The vendor for order %d is KO, fixing', $order->getId()));

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

    private function processTransferAmountAdjustments(OrderInterface $order)
    {
        $restaurants = $order->getRestaurants();

        if (count($restaurants) === 1) {
            return;
        }

        $rest = ($order->getTotal() - $order->getFeeTotal());

        foreach ($restaurants as $restaurant) {

            $transferAmount = ($rest * $order->getPercentageForRestaurant($restaurant));

            $transferAmountAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.transfer_amount'),
                $transferAmount,
                $neutral = true
            );
            $transferAmountAdjustment->setOriginCode(
                $restaurant->asOriginCode()
            );

            $order->addAdjustment($transferAmountAdjustment);
        }
    }
}
