<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class RuleHumanizer
{
	public function __construct(private ExpressionLanguage $expressionLanguage)
	{
	}

	public function humanize(PricingRule $rule)
	{
		$parsedExpression = $this->expressionLanguage->parse($rule->getExpression(), [
            'distance',
            'weight',
            'vehicle',
            'pickup',
            'dropoff',
            'packages',
            'order',
        ]);

        $rootNode = $parsedExpression->getNodes();

        $text = '';

        if ($rootNode->nodes['left']->attributes['name'] === 'distance') {
            if ($rootNode->attributes['operator'] === 'in') {
                $left = $rootNode->nodes['right']->nodes['left']->attributes['value'];
                $right = $rootNode->nodes['right']->nodes['right']->attributes['value'];

                $text .= sprintf('distance between %s and %s',
                    sprintf('%s km', number_format($left / 1000, 2)),
                    sprintf('%s km', number_format($right / 1000, 2))
                );
            }
        }

        return $text;
	}
}
