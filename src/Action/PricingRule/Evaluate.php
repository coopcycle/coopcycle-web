<?php

namespace AppBundle\Action\PricingRule;

use AppBundle\Api\Dto\PricingRuleEvaluate;
use AppBundle\Api\Dto\YesNoOutput;
use AppBundle\ExpressionLanguage\ExpressionLanguage;

class Evaluate
{
    private $expressionLanguage;

    public function __construct(ExpressionLanguage $expressionLanguage)
    {
        $this->expressionLanguage = $expressionLanguage;
    }

    public function __invoke(PricingRuleEvaluate $data)
    {
    	$delivery = $data->delivery;
    	$pricingRule = $data->pricingRule;

    	$output = new YesNoOutput();
    	$output->result = $pricingRule->matches($delivery, $this->expressionLanguage);

        return $output;
    }
}
