<?php

namespace AppBundle\Sylius\OrderProcessing;

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
            $this->logger->info('OrderFeeProcessor | skipped (no vendor)', ['order' => $this->loggingUtils->getOrderId($order)]);
            return;
        }

        $this->logger->info('OrderFeeProcessor | started', ['order' => $this->loggingUtils->getOrderId($order)]);

        $contract = $order->getVendorConditions()->getContract();

        $delivery = null;
        if (!$order->isTakeAway() && ($contract->isVariableDeliveryPriceEnabled() || $contract->isVariableCustomerAmountEnabled())) {
            try {
                $delivery = $this->getDelivery($order);
            } catch (ShippingAddressMissingException|NoAvailableTimeSlotException $e) {
                $this->logger->warning(sprintf('OrderFeeProcessor | error: %s',  $e->getMessage()),
                    ['order' => $this->loggingUtils->getOrderId($order)]);
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

                    $this->logger->warning(sprintf('OrderFeeProcessor | customer amount: %d | could not calculate price, falling back to flat price', $customerAmount),
                        ['order' => $this->loggingUtils->getOrderId($order)]);
                } else {
                    $this->logger->info(sprintf('OrderFeeProcessor | customer amount: %d | price calculated successfully', $customerAmount),
                        ['order' => $this->loggingUtils->getOrderId($order)]);
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

                    $this->logger->warning(sprintf('OrderFeeProcessor | business amount: %d | could not calculate price, falling back to flat price', $businessAmount),
                        ['order' => $this->loggingUtils->getOrderId($order)]);
                } else {
                    $this->logger->info(sprintf('OrderFeeProcessor | business amount: %d | price calculated successfully', $businessAmount),
                        ['order' => $this->loggingUtils->getOrderId($order)]);
                }
            }
        }

        $deliveryPromotionAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT);
        foreach ($deliveryPromotionAdjustments as $deliveryPromotionAdjustment) {
            if ($this->decreasePlatformFee($deliveryPromotionAdjustment)) {
                $businessAmount += $deliveryPromotionAdjustment->getAmount();

                $this->logger->info(sprintf('OrderFeeProcessor | business amount: %d | applied delivery promotion', $businessAmount),
                    ['order' => $this->loggingUtils->getOrderId($order)]);
            }
        }

        $orderPromotionAdjustments = $order->getAdjustments(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        foreach ($orderPromotionAdjustments as $orderPromotionAdjustment) {
            if ($this->decreasePlatformFee($orderPromotionAdjustment)) {
                $businessAmount += $orderPromotionAdjustment->getAmount();

                $this->logger->info(sprintf('OrderFeeProcessor | business amount: %d | applied order promotion', $businessAmount),
                    ['order' => $this->loggingUtils->getOrderId($order)]);
            }
        }

        $newTipAmount = $order->getTipAmount();

        // $order->getTipAmount() is the tip amount set in the current request
        // preserve the previous tip amount if $newTipAmount is null
        if (null !== $newTipAmount) {
            $this->upsertAdjustment(
                $order,
                AdjustmentInterface::TIP_ADJUSTMENT,
                'order.adjustment_type.tip',
                $newTipAmount,
                $neutral = false);
        }

        // If the order is fulfilled with delivery method,
        // the tip goes to the messenger
        if (!$order->isTakeAway()) {
            $tipAmount = $order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT);
            if ($tipAmount > 0) {
                $businessAmount += $tipAmount;

                $this->logger->info(sprintf('OrderFeeProcessor | business amount: %d | added tip', $businessAmount),
                    ['order' => $this->loggingUtils->getOrderId($order)]);
            }
        }

        // If the order contains LoopEat lunchboxes,
        // the platform collects the processing fees.
        if ($order->isLoopeat()) {
            $reusablePackagingTotal = $order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT);
            if ($reusablePackagingTotal > 0) {
                $businessAmount += $reusablePackagingTotal;

                $this->logger->info(sprintf('OrderFeeProcessor | business amount: %d | added reusable packaging fees', $businessAmount),
                    ['order' => $this->loggingUtils->getOrderId($order)]);
            }
        }

        $feeRate = $order->isTakeAway() ? $contract->getTakeAwayFeeRate() : $contract->getFeeRate();

        $feeAmount = (int) (($order->getItemsTotal() * $feeRate) + $businessAmount);

        // HOTFIX
        // When the promotion amount is higher than the platform fee,
        // make sure we don't add an adjustment with a negative amount
        if ($feeAmount < 0) {
            $feeAmount = 0;
        }

        $this->upsertAdjustment(
            $order,
            AdjustmentInterface::FEE_ADJUSTMENT,
            'order.adjustment_type.platform_fees',
            $feeAmount,
            true,
            [],
            true);

        $this->upsertAdjustment(
            $order,
            AdjustmentInterface::DELIVERY_ADJUSTMENT,
            'order.adjustment_type.delivery',
            $customerAmount,
            $neutral = false);

        $this->logger->info('OrderFeeProcessor | finished', ['order' => $this->loggingUtils->getOrderId($order)]);
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

    private function upsertAdjustment(
        BaseOrderInterface $order,
        string $type,
        string $labelId,
        int $amount,
        bool $neutral = false,
        array $details = [],
        bool $hasZeroValue = false)
    {
        if ($amount === 0 && !$hasZeroValue) {
            $order->removeAdjustments($type);
        } else {
            $prevAdjustments = $order->getAdjustments($type);

            if (count($prevAdjustments) === 0) {
                $adjustment = $this->adjustmentFactory->createWithData(
                    $type,
                    $this->translator->trans($labelId),
                    $amount,
                    $neutral,
                    $details
                );

                $order->addAdjustment($adjustment);

                $this->logger->info(sprintf('OrderFeeProcessor | %s: %d | added', $labelId, $adjustment->getAmount()),
                    ['order' => $this->loggingUtils->getOrderId($order)]);
            } else {
                //fixme: invalid state; do some cleanup?
                if (count($prevAdjustments) !== 1) {
                    $this->logger->warning(sprintf('OrderFeeProcessor | multiple %s: %d', $labelId, count($prevAdjustments)),
                        ['order' => $this->loggingUtils->getOrderId($order)]);
                }

                $adjustment = $prevAdjustments->first();
                $prevAmount = $adjustment->getAmount();
                $adjustment->setAmount($amount);

                $this->logger->info(sprintf('OrderFeeProcessor | %s: %d | prev: %d | updated', $labelId, $adjustment->getAmount(), $prevAmount),
                    ['order' => $this->loggingUtils->getOrderId($order)]);
            }
        }
    }
}
