<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Delivery;
use AppBundle\Exception\NoAvailableTimeSlotException;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Service\DeliveryManager;
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
        LoggerInterface $logger)
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
            return;
        }

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
                $this->logger->error(sprintf('OrderFeeProcessor | %s', $e->getMessage()));
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
                    $this->logger->error('OrderFeeProcessor | customer amount | could not calculate price, falling back to flat price');
                    $customerAmount = $contract->getCustomerAmount();
                } else {
                    $this->logger->info(sprintf('Order #%d | customer amount | matched rule "%s"',
                        $order->getId(), $this->deliveryManager->getLastMatchedRule()->getExpression()));
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
                    $this->logger->error('OrderFeeProcessor | business amount | could not calculate price, falling back to flat price');
                    $businessAmount = $contract->getFlatDeliveryPrice();
                } else {
                    $this->logger->info(sprintf('Order #%d | business amount | matched rule "%s"',
                        $order->getId(), $this->deliveryManager->getLastMatchedRule()->getExpression()));
                }
            }
        } else {
            $feeRate = $contract->getTakeAwayFeeRate();
        }

        $deliveryPromotionAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT);
        foreach ($deliveryPromotionAdjustments as $deliveryPromotionAdjustment) {
            if ($this->decreasePlatformFee($deliveryPromotionAdjustment)) {
                $businessAmount += $deliveryPromotionAdjustment->getAmount();
            }
        }

        $tipAmount = $order->getTipAmount();

        if (null !== $tipAmount) {
            if ($tipAmount > 0) {
                $tipAdjustment = $this->adjustmentFactory->createWithData(
                    AdjustmentInterface::TIP_ADJUSTMENT,
                    $this->translator->trans('order.adjustment_type.tip'),
                    $order->getTipAmount(),
                    $neutral = false
                );
                $order->addAdjustment($tipAdjustment);
            }
        } else {
            if (count($originalTipAdjustments) > 0) {
                foreach ($originalTipAdjustments as $tipAdjustment) {
                    $order->addAdjustment($tipAdjustment);
                }
            }
        }

        // If the order is fulfilled with delivery method,
        // the tip goes to the messenger
        if (!$order->isTakeAway()) {
            $businessAmount += $order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT);
        }

        $feeAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::FEE_ADJUSTMENT,
            $this->translator->trans('order.adjustment_type.platform_fees'),
            (int) (($order->getItemsTotal() * $feeRate) + $businessAmount),
            $neutral = true
        );
        $order->addAdjustment($feeAdjustment);

        if ($customerAmount > 0) {
            $deliveryAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::DELIVERY_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.delivery'),
                $customerAmount,
                $neutral = false
            );

            $order->addAdjustment($deliveryAdjustment);
        }
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
