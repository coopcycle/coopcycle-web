<?php

namespace AppBundle\Api\Dto;

use AppBundle\Pricing\RuleResult;
use Symfony\Component\Serializer\Annotation\Groups;

class CalculationItem
{
    /**
     * @param RuleResult[] $rules
     */
    public function __construct(
        #[Groups(['pricing_deliveries'])]
        public readonly string $target,
        #[Groups(['pricing_deliveries'])]
        public readonly array $rules,
    )
    {
    }
}
