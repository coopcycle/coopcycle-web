<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Node\FunctionNode;

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

        $accumulator = new \ArrayObject();

        $this->traverseNode($parsedExpression->getNodes(), $accumulator);

        return implode(', ', $accumulator->getArrayCopy());
	}

	private function traverseNode(Node $node, $accumulator)
	{
		if (isset($node->attributes['operator']) && $node->attributes['operator'] === 'and') {
        	$this->traverseNode($node->nodes['left'], $accumulator);
        	$this->traverseNode($node->nodes['right'], $accumulator);
        } else {
        	if ($node instanceof FunctionNode) {
        		if ($node->attributes['name'] === 'in_zone' || $node->attributes['name'] === 'out_zone') {
        			$accumulator[] = $this->humanizeZoneFunction($node);
        		}
        	} elseif ($node instanceof BinaryNode) {
        		$accumulator[] = $this->humanizeBinaryNode($node);
        	}
        }
	}

	private function humanizeZoneFunction(FunctionNode $node): string
	{
		$taskType = $node->nodes['arguments']->nodes[0]->nodes['node']->attributes['name'];
		$zoneName = $node->nodes['arguments']->nodes[1]->attributes['value'];
		$direction = $node->attributes['name'] === 'in_zone' ? 'inside' : 'outside';

		return sprintf('%s address %s zone "%s"', $taskType, $direction, $zoneName);
	}

	private function humanizeBinaryNode(BinaryNode $node): string
	{
		$attributeName = $node->nodes['left']->attributes['name'];

		if ($node->attributes['operator'] === 'in') {

			$left = $node->nodes['right']->nodes['left']->attributes['value'];
			$right = $node->nodes['right']->nodes['right']->attributes['value'];

			return sprintf('between %s and %s', $this->formatValue($left, $attributeName), $this->formatValue($right, $attributeName));

		} else {

			$rawValue = $node->nodes['right']->attributes['value'];
			$formattedValue = '';
			switch ($attributeName) {
				case 'weight':
					$formattedValue = sprintf('%s kg', number_format($rawValue / 1000, 2));
					break;
				case 'distance':
					$formattedValue = sprintf('%s km', number_format($rawValue / 1000, 2));
					break;
			}

			if ($node->attributes['operator'] === '<') {
				return sprintf('less than %s', $formattedValue);
			}
			if ($node->attributes['operator'] === '>') {
				return sprintf('more than %s', $formattedValue);
			}
		}
	}

	private function formatValue($value, $unit)
	{
		switch ($unit) {
			case 'weight':
				return sprintf('%s kg', number_format($value / 1000, 2));
			case 'distance':
				return sprintf('%s km', number_format($value / 1000, 2));
		}

		return $value;
	}
}
