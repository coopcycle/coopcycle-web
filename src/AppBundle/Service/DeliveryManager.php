<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class DeliveryManager
{
    private $expressionLanguage;

    public function __construct(ExpressionLanguage $expressionLanguage)
    {
        $this->expressionLanguage = $expressionLanguage;
    }

    public function getPrice(Delivery $delivery, PricingRuleSet $ruleSet)
    {
        foreach ($ruleSet->getRules() as $rule) {
            if ($rule->matches($delivery, $this->expressionLanguage)) {
                return $rule->evaluatePrice($delivery, $this->expressionLanguage);
            }
        }
    }
}
