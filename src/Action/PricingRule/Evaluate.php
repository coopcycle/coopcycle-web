<?php

namespace AppBundle\Action\PricingRule;

use AppBundle\Api\Dto\PricingRuleEvaluate;
use AppBundle\Api\Dto\YesNoOutput;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use AppBundle\Pricing\PriceCalculationVisitor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Evaluate
{
    public function __construct(
        private ExpressionLanguage $expressionLanguage,
        private readonly LoggerInterface $logger = new NullLogger()
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

        $visitor = new PriceCalculationVisitor($ruleSet, $this->expressionLanguage, $this->logger);
        $visitor->visit($delivery);

    	$output = new YesNoOutput();
    	$output->result = count($visitor->getMatchedRules()) > 0;

        return $output;
    }
}
