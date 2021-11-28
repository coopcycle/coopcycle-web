<?php

declare(strict_types=1);

namespace AppBundle\Sylius\Promotion\Action;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Promotion\Action\PromotionActionCommandInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Webmozart\Assert\Assert;

/**
 * @see https://github.com/Sylius/Sylius/blob/master/src/Sylius/Component/Core/Promotion/Action/PercentageDiscountPromotionActionCommand.php
 */
final class PercentageDiscountPromotionActionCommand implements PromotionActionCommandInterface
{
    public const TYPE = 'order_percentage_discount';

    /** @var AdjustmentFactoryInterface */
    private $adjustmentFactory;

    public function __construct(AdjustmentFactoryInterface $adjustmentFactory)
    {
        $this->adjustmentFactory = $adjustmentFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(PromotionSubjectInterface $subject, array $configuration, PromotionInterface $promotion): bool
    {
        if (!$subject instanceof OrderInterface) {
            throw new UnexpectedTypeException($subject, OrderInterface::class);
        }

        if (!isset($configuration['percentage'])) {
            return false;
        }

        $itemsTotal = $configuration['items_total'] ?? true;
        $amount = $itemsTotal ? $subject->getItemsTotal() : $subject->getTotal();

        $promotionAmount = -1 * (int) round($amount * $configuration['percentage']);

        if (0 === $promotionAmount) {
            return false;
        }

        $adjustment = $this->adjustmentFactory->createNew();
        $adjustment->setType(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        $adjustment->setLabel($promotion->getName());
        $adjustment->setOriginCode($promotion->getCode());
        $adjustment->setAmount($promotionAmount);

        $subject->addAdjustment($adjustment);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function revert(PromotionSubjectInterface $subject, array $configuration, PromotionInterface $promotion): void
    {
        if (!$subject instanceof OrderInterface) {
            throw new UnexpectedTypeException(
                $subject,
                OrderInterface::class
            );
        }

        foreach ($subject->getAdjustments(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT) as $adjustment) {
            if ($promotion->getCode() === $adjustment->getOriginCode()) {
                $subject->removeAdjustment($adjustment);
            }
        }
    }
}
