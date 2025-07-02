<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Node\FunctionNode;
use Symfony\Contracts\Translation\TranslatorInterface;

class RuleHumanizer
{
    public function __construct(
        private ExpressionLanguage $expressionLanguage,
        private TranslatorInterface $translator
    ) {
    }

    public function humanize(PricingRule $rule): string
    {
        try {
            $parsedExpression = $this->expressionLanguage->parse($rule->getExpression(), [
                'distance',
                'weight',
                'vehicle',
                'pickup',
                'dropoff',
                'packages',
                'order',
            ]);
        } catch (\Exception $e) {
            return $rule->getExpression();
        }

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
        $direction = $node->attributes['name'] === 'in_zone'
            ? $this->translator->trans('pricing.rule.humanizer.inside_zone')
            : $this->translator->trans('pricing.rule.humanizer.outside_zone');

        return $this->translator->trans('pricing.rule.humanizer.address_zone_format', [
            '%task_type%' => $taskType,
            '%direction%' => $direction,
            '%zone_name%' => $zoneName
        ]);
    }

    private function humanizeBinaryNode(BinaryNode $node): string
    {
        $attributeName = $node->nodes['left']->attributes['name'];

        if ($node->attributes['operator'] === 'in') {

            $left = $node->nodes['right']->nodes['left']->attributes['value'];
            $right = $node->nodes['right']->nodes['right']->attributes['value'];

            return $this->translator->trans('pricing.rule.humanizer.between', [
                '%left%' => $this->formatValue($left, $attributeName),
                '%right%' => $this->formatValue($right, $attributeName)
            ]);

        } else {

            $value = $node->nodes['right']->attributes['value'];
            if ($node->attributes['operator'] === '<') {
                return $this->translator->trans('pricing.rule.humanizer.less_than', [
                    '%value%' => $this->formatValue($value, $attributeName)
                ]);
            }
            if ($node->attributes['operator'] === '>') {
                return $this->translator->trans('pricing.rule.humanizer.more_than', [
                    '%value%' => $this->formatValue($value, $attributeName)
                ]);
            }

            //TODO: handle other operators
            return sprintf('%s', $this->formatValue($value, $attributeName));
        }
    }

    private function formatValue($value, $unit)
    {
        switch ($unit) {
            case 'weight':
                return $this->translator->trans('pricing.rule.humanizer.kg_unit', [
                    '%value%' => number_format($value / 1000, 2)
                ]);
            case 'distance':
                return $this->translator->trans('pricing.rule.humanizer.km_unit', [
                    '%value%' => number_format($value / 1000, 2)
                ]);
        }

        return $value;
    }
}
