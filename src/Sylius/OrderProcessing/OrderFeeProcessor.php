<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Delivery;
use AppBundle\Exception\NoAvailableTimeSlotException;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\LoggingUtils;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Promotion\Repository\PromotionRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class OrderFeeProcessor implements OrderProcessorInterface
{
    private $adjustmentFactory;
    private $translator;
    private $deliveryManager;
    private $promotionRepository;
    private $logger;

    public function __construct(
        AdjustmentFactoryInterface $adjustmentFactory,
        TranslatorInterface $translator,
        DeliveryManager $deliveryManager,
        PromotionRepositoryInterface $promotionRepository,
        LoggerInterface $logger,
        private LoggingUtils $loggingUtils
    )
    {
        $this->adjustmentFactory = $adjustmentFactory;
        $this->translator = $translator;
        $this->deliveryManager = $deliveryManager;
        $this->promotionRepository = $promotionRepository;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if (!$order->hasVendor()) {
            $this->logger->info(sprintf('Order %s | OrderFeeProcessor | skipped (no vendor)',
                $this->loggingUtils->getOrderId($order)));
            return;
        }

        $this->logger->info(sprintf('Order %s | OrderFeeProcessor | started | triggered by %s',
            $this->loggingUtils->getOrderId($order), $this->loggingUtils->getBacktrace()));

        $originalTipAdjustments = $order->getAdjustments(AdjustmentInterface::TIP_ADJUSTMENT);

        $order->removeAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT);
        $order->removeAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);
        $order->removeAdjustments(AdjustmentInterface::TIP_ADJUSTMENT);

        $contract = $order->getVendor()->getContract();
        $feeRate = $contract->getFeeRate();

        $delivery = null;
        if (!$order->isTakeAway() && ($contract->isVariableDeliveryPriceEnabled() || $contract->isVariableCustomerAmountEnabled())) {
            try {
                $delivery = $this->getDelivery($order);
            } catch (ShippingAddressMissingException|NoAvailableTimeSlotException $e) {
                $this->logger->error(sprintf('Order %s | OrderFeeProcessor | error: %s',
                    $this->loggingUtils->getOrderId($order),  $e->getMessage()));
            }
        }

        $customerAmount = 0;

        if (!$order->isTakeAway()) {

            $customerAmount = $contract->getCustomerAmount();

            if ($contract->isVariableCustomerAmountEnabled() && null !== $delivery) {

                $customerAmount = $this->deliveryManager->getPrice(
                    $delivery,
                    $contract->getVariableCustomerAmount()
                );
                if (null === $customerAmount) {
                    $customerAmount = $contract->getCustomerAmount();

                    $this->logger->error(sprintf('Order %s | OrderFeeProcessor | customer amount: %d | could not calculate price, falling back to flat price',
                        $this->loggingUtils->getOrderId($order), $customerAmount));
                } else {
                    $this->logger->info(sprintf('Order %s | OrderFeeProcessor | customer amount: %d | price calculated successfully',
                        $this->loggingUtils->getOrderId($order), $customerAmount));
                }
            }
        }

        $businessAmount = 0;

        if (!$order->isTakeAway()) {

            $businessAmount = $contract->getFlatDeliveryPrice();

            if ($contract->isVariableDeliveryPriceEnabled() && null !== $delivery) {
                $businessAmount = $this->deliveryManager->getPrice(
                    $delivery,
                    $contract->getVariableDeliveryPrice()
                );
                if (null === $businessAmount) {
                    $businessAmount = $contract->getFlatDeliveryPrice();

                    $this->logger->error(sprintf('Order %s | OrderFeeProcessor | business amount: %d | could not calculate price, falling back to flat price',
                        $this->loggingUtils->getOrderId($order), $businessAmount));
                } else {
                    $this->logger->info(sprintf('Order %s | OrderFeeProcessor | business amount: %d | price calculated successfully',
                        $this->loggingUtils->getOrderId($order), $businessAmount));
                }
            }
        } else {
            $feeRate = $contract->getTakeAwayFeeRate();
        }

        $deliveryPromotionAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT);
        foreach ($deliveryPromotionAdjustments as $deliveryPromotionAdjustment) {
            if ($this->decreasePlatformFee($deliveryPromotionAdjustment)) {
                $businessAmount += $deliveryPromotionAdjustment->getAmount();

                $this->logger->info(sprintf('Order %s | OrderFeeProcessor | business amount: %d | applied delivery promotion',
                    $this->loggingUtils->getOrderId($order), $businessAmount));
            }
        }

        $orderPromotionAdjustments = $order->getAdjustments(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        foreach ($orderPromotionAdjustments as $orderPromotionAdjustment) {
            if ($this->decreasePlatformFee($orderPromotionAdjustment)) {
                $businessAmount += $orderPromotionAdjustment->getAmount();

                $this->logger->info(sprintf('Order %s | OrderFeeProcessor | business amount: %d | applied order promotion',
                    $this->loggingUtils->getOrderId($order), $businessAmount));
            }
        }

        $tipAmount = $order->getTipAmount();

        if (null !== $tipAmount) {
            if ($tipAmount > 0) {
                $tipAdjustment = $this->adjustmentFactory->createWithData(
                    AdjustmentInterface::TIP_ADJUSTMENT,
                    $this->translator->trans('order.adjustment_type.tip'),
                    $tipAmount,
                    $neutral = false
                );
                $order->addAdjustment($tipAdjustment);

                $this->logger->info(sprintf('Order %s | OrderFeeProcessor | tip amount: %d | added',
                    $this->loggingUtils->getOrderId($order), $tipAmount));
            }
        } else {
            if (count($originalTipAdjustments) > 0) {
                foreach ($originalTipAdjustments as $tipAdjustment) {
                    $order->addAdjustment($tipAdjustment);

                    $this->logger->info(sprintf('Order %s | OrderFeeProcessor | tip amount: %d | re-used previous tip adjustment',
                        $this->loggingUtils->getOrderId($order), $tipAdjustment->getAmount()));
                }
            }
        }

        // If the order is fulfilled with delivery method,
        // the tip goes to the messenger
        if (!$order->isTakeAway()) {
            $businessAmount += $order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT);

            $this->logger->info(sprintf('Order %s | OrderFeeProcessor | business amount: %d | added tip',
                $this->loggingUtils->getOrderId($order), $businessAmount));
        }

        // If the order contains LoopEat lunchboxes,
        // the platform collects the processing fees.
        if ($order->isLoopeat()) {
            $reusablePackagingTotal = $order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT);
            if ($reusablePackagingTotal > 0) {
                $businessAmount += $reusablePackagingTotal;

                $this->logger->info(sprintf('Order %s | OrderFeeProcessor | business amount: %d | added reusable packaging fees',
                    $this->loggingUtils->getOrderId($order), $businessAmount));
            }
        }

        $feeAmount = (int) (($order->getItemsTotal() * $feeRate) + $businessAmount);

        // HOTFIX
        // When the promotion amount is higher than the platform fee,
        // make sure we don't add an adjustment with a negative amount
        if ($feeAmount < 0) {
            $feeAmount = 0;
        }

        $feeAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::FEE_ADJUSTMENT,
            $this->translator->trans('order.adjustment_type.platform_fees'),
            $feeAmount,
            $neutral = true
        );
        $order->addAdjustment($feeAdjustment);

        $this->logger->info(sprintf('Order %s | OrderFeeProcessor | fee adjustment: %d | added',
            $this->loggingUtils->getOrderId($order), $feeAdjustment->getAmount()));

        if ($customerAmount > 0) {
            $deliveryAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::DELIVERY_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.delivery'),
                $customerAmount,
                $neutral = false
            );

            $order->addAdjustment($deliveryAdjustment);

            $this->logger->info(sprintf('Order %s | OrderFeeProcessor | delivery adjustment: %d | added',
                $this->loggingUtils->getOrderId($order), $deliveryAdjustment->getAmount()));
        }

        $this->logger->info(sprintf('Order %s | OrderFeeProcessor | finished',
            $this->loggingUtils->getOrderId($order)));
    }

    private function decreasePlatformFee(Adjustment $adjustment): bool
    {
        $promotion = $this->promotionRepository
            ->findOneBy(['code' => $adjustment->getOriginCode()]);

        if (!$promotion) {

            return true;
        }

        foreach ($promotion->getActions() as $action) {
            $configuration = $action->getConfiguration();
            if (isset($configuration['decrase_platform_fee'])) {

                return $configuration['decrase_platform_fee'];
            }
        }

        return true;
    }

    private function getDelivery(OrderInterface $order)
    {
        $delivery = $order->getDelivery();

        if (null === $order->getDelivery()) {

            return $this->deliveryManager->createFromOrder($order);
        }

        return $delivery;
    }
}
