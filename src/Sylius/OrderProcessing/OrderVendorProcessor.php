<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class OrderVendorProcessor implements OrderProcessorInterface
{
    private $adjustmentFactory;
    private $translator;
    private $logger;

    public function __construct(
        AdjustmentFactoryInterface $adjustmentFactory,
        TranslatorInterface $translator,
        LoggerInterface $logger)
    {
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

        $vendor = $order->getVendor();

        if (!$vendor->isHub()) {
            return;
        }

        $subVendors = $order->getVendors();

        if (count($subVendors) === 1) {
            return;
        }

        $hub = $vendor->getHub();

        $rest = ($order->getTotal() - $order->getFeeTotal());

        foreach ($subVendors as $subVendor) {

            $percentageForVendor = $hub->getPercentageForRestaurant($order, $subVendor);
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
