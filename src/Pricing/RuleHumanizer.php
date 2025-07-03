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
                'task',
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
        if ($node instanceof BinaryNode && isset($node->attributes['operator']) && $node->attributes['operator'] === 'and') {
            // Handle 'and' operations by recursively processing left and right sides
            $this->traverseNode($node->nodes['left'], $accumulator);
            $this->traverseNode($node->nodes['right'], $accumulator);
        } elseif ($node instanceof FunctionNode) {
            // Handle function calls
            if ($node->attributes['name'] === 'in_zone' || $node->attributes['name'] === 'out_zone') {
                $accumulator[] = $this->humanizeZoneFunction($node);
            } elseif ($node->attributes['name'] === 'time_range_length') {
                $accumulator[] = $this->humanizeTimeRangeLengthFunction($node);
            } elseif ($node->attributes['name'] === 'diff_hours') {
                $accumulator[] = $this->humanizeDiffFunction($node, 'basics.hours');
            } elseif ($node->attributes['name'] === 'diff_days') {
                $accumulator[] = $this->humanizeDiffFunction($node, 'basics.days');
            }
        } elseif ($node instanceof BinaryNode) {
            // Handle all other binary operations (comparisons, etc.)
            $accumulator[] = $this->humanizeBinaryNode($node);
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

    private function humanizeTimeRangeLengthFunction(FunctionNode $node): string
    {
        /*
         * Function call structure for time_range_length(dropoff, 'hours', '< 1.5'):
         * - $node->attributes['name'] = 'time_range_length'
         * - $node->nodes['arguments']->nodes[0] = NameNode with name 'dropoff'
         * - $node->nodes['arguments']->nodes[1] = ConstantNode with value 'hours'
         * - $node->nodes['arguments']->nodes[2] = ConstantNode with value '< 1.5'
         */


        $taskTypeName = $node->nodes['arguments']->nodes[0]->attributes['name'] ??
            $node->nodes['arguments']->nodes[0]->attributes['value'] ?? 'unknown';
        $unit = $node->nodes['arguments']->nodes[1]->attributes['value'];
        $condition = $node->nodes['arguments']->nodes[2]->attributes['value'];

        $taskType = $this->translateTaskTypeName($taskTypeName);

        if (preg_match('/^in\s+(\d+(?:\.\d+)?)\.\.(\d+(?:\.\d+)?)$/', $condition, $matches)) {
            // Handle range condition like "in 0..1"

            $value = $this->translator->trans('pricing.rule.humanizer.between', [
                '%left%' => $this->translator->trans('basics.hours', ['%count%' => $matches[1]]),
                '%right%' => $this->translator->trans('basics.hours', ['%count%' => $matches[2]]),
            ]);

            return $this->translator->trans('pricing.rule.humanizer.time_range_length', [
                '%task_type%' => $taskType,
                '%operator%' => $value,
                '%value%' => '',
            ]);

        } else {
            // Parse condition like "< 1.5"
            preg_match('/([<>=]+)\s*(.+)/', $condition, $matches);
            $operator = $this->translateOperator($matches[1] ?? '<');
            $value = $matches[2] ?? $condition;

            return $this->translator->trans('pricing.rule.humanizer.time_range_length', [
                '%task_type%' => $taskType,
                '%operator%' => $operator,
                '%value%' => $this->translator->trans('basics.hours', ['%count%' => $value]),
            ]);
        }
    }

    private function humanizeDiffFunction(FunctionNode $node, string $unitTranslationKey): string
    {
        /*
         * Function call structure for diff_hours(pickup, '< 12'):
         * - $node->attributes['name'] = 'diff_hours'
         * - $node->nodes['arguments']->nodes[0] = NameNode with name 'pickup'
         * - $node->nodes['arguments']->nodes[1] = ConstantNode with value '< 12'
         */

        $taskTypeName = $node->nodes['arguments']->nodes[0]->attributes['name'] ??
            $node->nodes['arguments']->nodes[0]->attributes['value'] ?? 'unknown';
        $condition = $node->nodes['arguments']->nodes[1]->attributes['value'];

        $taskType = $this->translateTaskTypeName($taskTypeName);

        if (preg_match('/^in\s+(\d+(?:\.\d+)?)\.\.(\d+(?:\.\d+)?)$/', $condition, $matches)) {
            // Handle range condition like "in 0..1"

            $value = $this->translator->trans('pricing.rule.humanizer.between', [
                '%left%' => $this->translator->trans($unitTranslationKey, ['%count%' => $matches[1]]),
                '%right%' => $this->translator->trans($unitTranslationKey, ['%count%' => $matches[2]]),
            ]);

            return $this->translator->trans('pricing.rule.humanizer.diff', [
                '%task_type%' => $taskType,
                '%operator%' => $value,
                '%value%' => '',
            ]);

        } else {
            // Handle standard operators like "<", ">", etc.
            preg_match('/([<>=]+)\s*(.+)/', $condition, $matches);
            $operator = $this->translateOperator($matches[1] ?? '<');
            $value = $matches[2] ?? $condition;

            return $this->translator->trans('pricing.rule.humanizer.diff', [
                '%task_type%' => $taskType,
                '%operator%' => $operator,
                '%value%' => $this->translator->trans($unitTranslationKey, ['%count%' => $value]),
            ]);
        }
    }

    private function humanizeBinaryNode(BinaryNode $node): string
    {
        /*
         * For simple property access like 'distance' or 'weight':
         * - $node->nodes['left']->attributes['name'] contains the property name
         *
         * For object property access like 'task.type' or 'order.itemsTotal':
         * - $node->nodes['left'] is a GetAttrNode
         * - $node->nodes['left']->nodes['node']->attributes['name'] contains the object name (e.g., 'task')
         * - $node->nodes['left']->nodes['attribute']->attributes['value'] contains the property name (e.g., 'type')
         *
         * For method calls like 'packages.totalVolumeUnits()':
         * - $node->nodes['left'] is a GetAttrNode with type: 2 (method call)
         * - $node->nodes['left']->nodes['node']->attributes['name'] contains the object name (e.g., 'packages')
         * - $node->nodes['left']->nodes['attribute']->attributes['value'] contains the method name (e.g., 'totalVolumeUnits')
         *
         * For comparison operators:
         * - $node->attributes['operator'] contains the operator ('==', '<', '>', 'in', etc.)
         * - $node->nodes['right'] contains the value being compared to
         */

        if (isset($node->nodes['left']->attributes['name'])) {

            // Simple property access like 'distance' or 'weight'
            $attributeName = $node->nodes['left']->attributes['name'];

        } elseif (isset($node->nodes['left']->nodes['node']->attributes['name']) &&
                  isset($node->nodes['left']->nodes['attribute']->attributes['value'])) {
            // Handle object property access like task.type, order.itemsTotal (GetAttrNode)
            $objectName = $node->nodes['left']->nodes['node']->attributes['name'];
            $propertyName = $node->nodes['left']->nodes['attribute']->attributes['value'];
            $attributeName = $objectName . '.' . $propertyName;

            // Check if this is a method call (type: 2) for packages.totalVolumeUnits()
            if (isset($node->nodes['left']->attributes['type']) &&
                $node->nodes['left']->attributes['type'] === 2 &&
                $objectName === 'packages' &&
                $propertyName === 'totalVolumeUnits') {
                return $this->humanizePackagesTotalVolumeUnits($node->attributes['operator'], $node->nodes['right']->attributes['value']);
            }

        } else {
            $attributeName = 'unknown';
        }

        if ($node->attributes['operator'] === 'in') {

            $left = $node->nodes['right']->nodes['left']->attributes['value'];
            $right = $node->nodes['right']->nodes['right']->attributes['value'];

            return $this->translator->trans('pricing.rule.humanizer.between', [
                '%left%' => $this->formatValue($left, $attributeName),
                '%right%' => $this->formatValue($right, $attributeName)
            ]);

        } else {

            $value = $node->nodes['right']->attributes['value'];

            if ($attributeName === 'task.type' && $node->attributes['operator'] === '==') {
                return $this->humanizeTaskType($value);
            } elseif ($attributeName === 'order.itemsTotal') {
                return $this->humanizeOrderItemsTotal($node->attributes['operator'], $value);
            } elseif ($node->attributes['operator'] === '<') {
                return $this->translator->trans('pricing.rule.humanizer.less_than', [
                    '%value%' => $this->formatValue($value, $attributeName)
                ]);
            } elseif ($node->attributes['operator'] === '>') {
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

    private function humanizeTaskType(string $taskType): string
    {
        return $this->translator->trans('pricing.rule.humanizer.task_type_is', [
            '%value%' => $this->translateTaskTypeName($taskType)
        ]);
    }

    private function humanizePackagesTotalVolumeUnits(string $operator, $value): string
    {
        $translatedOperator = $this->translateOperator($operator);

        return $this->translator->trans('pricing.rule.humanizer.packages_volume_units', [
            '%operator%' => $translatedOperator,
            '%value%' => $value
        ]);
    }

    private function humanizeOrderItemsTotal(string $operator, $value): string
    {
        $translatedOperator = $this->translateOperator($operator);

        return $this->translator->trans('pricing.rule.humanizer.order_items_total', [
            '%operator%' => $translatedOperator,
            '%value%' => $value
        ]);
    }

    private function translateOperator(string $operator): string
    {
        switch ($operator) {
            case '<':
                // Extract just the operator part from the translation
                $translation = $this->translator->trans('pricing.rule.humanizer.less_than', ['%value%' => '']);
                return trim(str_replace('%value%', '', $translation));
            case '>':
                // Extract just the operator part from the translation
                $translation = $this->translator->trans('pricing.rule.humanizer.more_than', ['%value%' => '']);
                return trim(str_replace('%value%', '', $translation));
            case '==':
                return '=';
            default:
                return $operator;
        }
    }

    private function translateTaskTypeName(string $taskTypeName): string
    {
        switch (strtolower($taskTypeName)) {
            case 'pickup':
                return strtolower($this->translator->trans('task.type.PICKUP'));
            case 'dropoff':
                return strtolower($this->translator->trans('task.type.DROPOFF'));
            default:
                return $taskTypeName;
        }
    }
}
