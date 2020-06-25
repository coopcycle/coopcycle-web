<?php

declare(strict_types=1);

namespace AppBundle\Sylius\Promotion\Action;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Model\AdjustmentInterface as OrderAdjustmentInterface;
use Sylius\Component\Promotion\Action\PromotionActionCommandInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class FixedDiscountPromotionActionCommand implements PromotionActionCommandInterface
{
    public const TYPE = 'order_fixed_discount';

    /** @var FactoryInterface */
    private $adjustmentFactory;

    public function __construct(FactoryInterface $adjustmentFactory)
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

        if (!isset($configuration['amount'])) {
            return false;
        }

        $adjustment = $this->createAdjustment($promotion);
        $adjustment->setAmount((int) $configuration['amount'] * -1);

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

    private function createAdjustment(
        PromotionInterface $promotion,
        string $type = AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT
    ): OrderAdjustmentInterface {
        /** @var OrderAdjustmentInterface $adjustment */
        $adjustment = $this->adjustmentFactory->createNew();
        $adjustment->setType($type);
        $adjustment->setLabel($promotion->getName());
        $adjustment->setOriginCode($promotion->getCode());

        return $adjustment;
    }
}
