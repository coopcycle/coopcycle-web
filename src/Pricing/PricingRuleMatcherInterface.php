<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

interface PricingRuleMatcherInterface
{
    public function matchesPricingRule(PricingRule $pricingRule, ExpressionLanguage $language = null);
    public function toExpressionLanguageValues();
}
