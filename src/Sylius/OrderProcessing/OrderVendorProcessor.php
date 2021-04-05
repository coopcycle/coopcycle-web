<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\HubRepository;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class OrderVendorProcessor implements OrderProcessorInterface
{
    private $hubRepository;
    private $localBusinessRepository;
    private $adjustmentFactory;
    private $translator;
    private $logger;

    public function __construct(
        HubRepository $hubRepository,
        LocalBusinessRepository $localBusinessRepository,
        AdjustmentFactoryInterface $adjustmentFactory,
        TranslatorInterface $translator,
        LoggerInterface $logger)
    {
        $this->hubRepository = $hubRepository;
        $this->localBusinessRepository = $localBusinessRepository;
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

        $vendor = $this->processUpgradeOrDowngrade($order);
        $order->setVendor($vendor);

        if ($vendor->isHub()) {
            $this->processTransferAmountAdjustments($order);
        }
    }

    private function processUpgradeOrDowngrade(OrderInterface $order): Vendor
    {
        $this->logger->debug(sprintf('Checking if order #%d needs vendor upgrade/downgrade', $order->getId()));

        $vendor = $order->getVendor();
        $restaurants = $this->getRestaurants($order);

        $this->logger->debug(sprintf('There are %d vendors in order #%d', count($restaurants), $order->getId()));

        if (count($restaurants) === 0) {

            return $vendor;
        }

        if (count($restaurants) === 1) {

            // Make sure the vendor matches

            $restaurant = $restaurants->current();

            if ($vendor->getRestaurant() === $restaurant) {
                $this->logger->debug(sprintf('The vendor for order %d is OK, skipping', $order->getId()));

                return $vendor;
            }

            $this->logger->debug(sprintf('The vendor for order %d is KO, fixing', $order->getId()));

            return Vendor::withRestaurant($restaurant);
        }

        //
        // Upgrade if needed
        //

        $hubs = new \SplObjectStorage();

        foreach ($restaurants as $restaurant) {
            $hub = $this->hubRepository->findOneByRestaurant($restaurant);
            if (!$hubs->contains($hub)) {
                $hubs->attach($hub);
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
            $restaurant = $this->localBusinessRepository->findOneByProduct(
                $item->getVariant()->getProduct()
            );

            if ($restaurant && !$restaurants->contains($restaurant)) {
                $restaurants->attach($restaurant);
            }
        }

        return $restaurants;
    }

    private function processTransferAmountAdjustments(OrderInterface $order)
    {
        $vendor = $order->getVendor();
        $subVendors = $order->getVendors();

        if (count($subVendors) === 1) {
            return;
        }

        $hub = $vendor->getHub();

        $rest = ($order->getTotal() - $order->getFeeTotal());

        foreach ($subVendors as $subVendor) {

            $percentageForVendor = $order->getPercentageForRestaurant($subVendor);
            $transferAmount = ($rest * $percentageForVendor);

            $transferAmountAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.transfer_amount'),
                $transferAmount,
                $neutral = true
            );
            $transferAmountAdjustment->setOriginCode(
                $subVendor->asOriginCode()
            );

            $order->addAdjustment($transferAmountAdjustment);
        }
    }
}
