<?php

namespace AppBundle\Sylius\OrderProcessing;

use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use AppBundle\Entity\ReusablePackagings;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\LoopEat\Client as LoopeatClient;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OrderDepositRefundProcessor implements OrderProcessorInterface
{
    /**
     * Always add Loopeat processing fee
     */
    public const LOOPEAT_PROCESSING_FEE_BEHAVIOR_ALWAYS = 'always';

    /**
     * Add Loopeat processing fee when customers returns containers
     */
    public const LOOPEAT_PROCESSING_FEE_BEHAVIOR_ON_RETURNS = 'on_returns';

    public function __construct(
        private AdjustmentFactoryInterface $adjustmentFactory,
        private TranslatorInterface $translator,
        private LoopeatClient $loopeatClient,
        private int $loopeatProcessingFee = 0,
        private string $loopeatProcessingFeeBehavior = self::LOOPEAT_PROCESSING_FEE_BEHAVIOR_ALWAYS,
        private $loopeatToteBagId = null)
    {
        $this->loopeatProcessingFee = $loopeatProcessingFee;

        if (!in_array($loopeatProcessingFeeBehavior, [self::LOOPEAT_PROCESSING_FEE_BEHAVIOR_ALWAYS, self::LOOPEAT_PROCESSING_FEE_BEHAVIOR_ON_RETURNS])) {
            throw new \InvalidArgumentException(sprintf('$loopeatProcessingFeeBehavior should have value "%s" or "%s"', self::LOOPEAT_PROCESSING_FEE_BEHAVIOR_ALWAYS, self::LOOPEAT_PROCESSING_FEE_BEHAVIOR_ON_RETURNS));
        }

        $this->loopeatProcessingFeeBehavior = $loopeatProcessingFeeBehavior;
    }

    public function setLoopeatProcessingFeeBehavior($loopeatProcessingFeeBehavior)
    {
        $this->loopeatProcessingFeeBehavior = $loopeatProcessingFeeBehavior;
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        $order->removeAdjustmentsRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT);

        // For the moment, not supported on hubs
        if (!$order->hasVendor() || $order->isMultiVendor()) {
            return;
        }

        $restaurant = $order->getRestaurant();

        if (!$restaurant->isDepositRefundEnabled() && !$restaurant->isLoopeatEnabled() && !$restaurant->isDabbaEnabled()) {
            return;
        }

        if ($restaurant->isDepositRefundOptin()) {
            if (!$order->isReusablePackagingEnabled()) {
                return;
            }
        }

        $totalAmount = 0;
        foreach ($order->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                if (!$product->hasReusablePackagings()) {
                    continue;
                }

                $reusablePackagings = $product->getReusablePackagings();

                foreach ($reusablePackagings as $reusablePackaging) {

                    $pkg = $reusablePackaging->getReusablePackaging();
                    $units = $this->getUnits($order, $item, $reusablePackaging, $pkg);

                    $label = $pkg->getAdjustmentLabel($this->translator, $units);
                    $amount = $pkg->getPrice() * $units;

                    $item->addAdjustment($this->adjustmentFactory->createWithData(
                        AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                        $label,
                        $amount,
                        $neutral = true
                    ));

                    $totalAmount += $amount;
                }
            }
        }

        // Collect an additional fee for LoopEat, *PER ORDER*
        // https://github.com/coopcycle/coopcycle-web/issues/2284
        if ($restaurant->isLoopeatEnabled() && $this->loopeatProcessingFee > 0) {
            if (self::LOOPEAT_PROCESSING_FEE_BEHAVIOR_ALWAYS === $this->loopeatProcessingFeeBehavior
                || (self::LOOPEAT_PROCESSING_FEE_BEHAVIOR_ON_RETURNS === $this->loopeatProcessingFeeBehavior && $order->hasLoopeatReturns())) {
                $order->addAdjustment($this->adjustmentFactory->createWithData(
                    AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                    $this->translator->trans('order.adjustment_type.reusable_packaging.loopeat'),
                    $this->loopeatProcessingFee,
                    neutral: false,
                    details: ['loopeat_processing_fee' => true]
                ));
            }
        } else if ($totalAmount > 0) {
            $order->addAdjustment($this->adjustmentFactory->createWithData(
                AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.reusable_packaging'),
                $totalAmount,
                neutral: false
            ));
        }

        if ($restaurant->isLoopeatEnabled() && !empty($this->loopeatToteBagId)) {
            $formats = $this->loopeatClient->getFormats($restaurant);
            foreach ($formats as $format) {
                if ($format['id'] === (int) $this->loopeatToteBagId) {
                    $order->addAdjustment($this->adjustmentFactory->createWithData(
                        AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                        sprintf('%s − %s', $format['title'], $format['subtitle']),
                        $format['cost_cents'],
                        neutral: false
                    ));
                }
            }
        }
    }

    private function getUnits(
        BaseOrderInterface $order,
        OrderItemInterface $item,
        ReusablePackagings $reusablePackaging,
        ReusablePackaging $pkg): float
    {
        $restaurant = $order->getRestaurant();

        if ($restaurant->isLoopeatEnabled()) {
            $pkgData = $pkg->getData();
            $loopeatDeliver = $order->getLoopeatDeliver();
            if (isset($loopeatDeliver[$item->getId()])) {
                foreach ($loopeatDeliver[$item->getId()] as $loopeatDeliverFormat) {
                    if ($loopeatDeliverFormat['format_id'] === $pkgData['id']) {

                        return $loopeatDeliverFormat['quantity'];
                    }
                }
            }
        }

        return ceil($reusablePackaging->getUnits() * $item->getQuantity());
    }
}
