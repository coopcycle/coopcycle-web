<?php

namespace AppBundle\Action\PricingRule;

use AppBundle\Api\Dto\PricingRuleEvaluate;
use AppBundle\Api\Dto\YesNoOutput;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Pricing\PriceCalculationVisitor;

class Evaluate
{
    public function __construct(
        private readonly PriceCalculationVisitor $priceCalculationVisitor,
    )
    {
    }

    public function __invoke(PricingRuleEvaluate $data)
    {
    	$delivery = $data->delivery;
        /** @var PricingRule $pricingRule */
    	$pricingRule = $data->pricingRule;

        $pricingRuleSet = $pricingRule->getRuleSet();

        // clone the pricing rule set with only one rule that we want to evaluate
        $ruleSet = clone $pricingRuleSet;
        $ruleSet->setRules([$pricingRule]);

        $result = $this->priceCalculationVisitor->visit($delivery, $ruleSet);

    	$output = new YesNoOutput();
    	$output->result = count($result->matchedRules) > 0;

        return $output;
    }
}
