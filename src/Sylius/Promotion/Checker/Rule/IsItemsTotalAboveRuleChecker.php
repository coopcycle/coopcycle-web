<?php

namespace AppBundle\Sylius\Promotion\Checker\Rule;

use Sylius\Component\Promotion\Checker\Rule\RuleCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;

class IsItemsTotalAboveRuleChecker implements RuleCheckerInterface
{
    const TYPE = 'is_items_total_above';

    /**
     * {@inheritdoc}
     */
    public function isEligible(PromotionSubjectInterface $subject, array $configuration): bool
    {
        if (!isset($configuration['amount'])) {
            return false;
        }

        return $subject->getItemsTotal() >= $configuration['amount'];
    }
}
