<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Delivery\PricingRuleSet;
use Symfony\Component\Serializer\Annotation\Groups;

class CalculationOutput
{
    /**
     * @param CalculationItem[] $items
     */
    public function __construct(
        #[Groups(['pricing_deliveries'])]
        public readonly PricingRuleSet $ruleSet,
        #[Groups(['pricing_deliveries'])]
        public readonly string $strategy,
        #[Groups(['pricing_deliveries'])]
        public readonly array $items,
    )
    {
    }
}
