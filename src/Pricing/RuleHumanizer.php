<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use AppBundle\Pricing\PriceExpressions\FixedPriceExpression;
use AppBundle\Pricing\PriceExpressions\PriceExpression;
use AppBundle\Pricing\PriceExpressions\PricePercentageExpression;
use AppBundle\Pricing\PriceExpressions\PricePerPackageExpression;
use AppBundle\Pricing\PriceExpressions\PriceRangeExpression;
use AppBundle\Utils\PriceFormatter;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Node\FunctionNode;
use Symfony\Contracts\Translation\TranslatorInterface;

class RuleHumanizer
{
    const FAILED_TO_PARSE = 'Failed to parse';

    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly PriceExpressionParser $priceExpressionParser,
        private readonly TranslatorInterface $translator,
        private readonly PriceFormatter $priceFormatter,
    ) {
    }

    public function humanize(PricingRule $rule): string
    {
        $parsedExpression = $this->expressionLanguage->parseRuleExpression($rule->getExpression());

        $parsedPrice = $this->priceExpressionParser->parsePrice($rule->getPrice());
        $pricePart = $this->humanizePriceExpression($parsedPrice, $rule->getPrice());

        if (null === $parsedExpression) {
            return $this->withPrice($this->fallbackName($rule), $pricePart);
        }

        $accumulator = new \ArrayObject();

        $this->traverseNode($parsedExpression->getNodes(), $accumulator);

        $parts = $accumulator->getArrayCopy();

        // @phpstan-ignore-next-line Result of || is always true. (False positive: it is not when traverseNode populates accumulator)
        if (0 === count($parts) || in_array(self::FAILED_TO_PARSE, $parts)) {
            return $this->withPrice($this->fallbackName($rule), $pricePart);
        }

        // @phpstan-ignore-next-line deadCode.unreachable (False positive: line is reachable when traverseNode populates accumulator)
        return $this->withPrice(ucfirst(implode(', ', $parts)), $pricePart);
    }

    private function fallbackName(PricingRule $rule)
    {
        return $rule->getExpression();
    }

    private function withPrice(string $name, string $pricePart): string
    {
        return $name.' - '.$pricePart;
    }

    /**
     * Recursively traverse expression nodes and accumulate human-readable parts.
     *
     * @param Node $node The expression node to traverse
     * @param \ArrayObject $accumulator The accumulator that collects human-readable parts
     */
    private function traverseNode(Node $node, \ArrayObject $accumulator): void
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
            } else {
                $accumulator[] = self::FAILED_TO_PARSE;
            }
        } elseif ($node instanceof GetAttrNode) {
            // Handle object property access like packages.containsAtLeastOne
            $objectName = $node->nodes['node']->attributes['name'];
            $propertyName = $node->nodes['attribute']->attributes['value'];

            if ($objectName === 'packages' && $propertyName === 'containsAtLeastOne') {
                $argument = $node->nodes['arguments']->nodes[1]->attributes['value'];
                $accumulator[] = $this->translator->trans('pricing.rule.humanizer.packages_contains_at_least_one', [
                    '%package_name%' => $argument,
                ]);
            } else {
                $accumulator[] = self::FAILED_TO_PARSE;
            }
        } elseif ($node instanceof BinaryNode) {
            // Handle all other binary operations (comparisons, etc.)
            $accumulator[] = $this->humanizeBinaryNode($node);
        } else {
            $accumulator[] = self::FAILED_TO_PARSE;
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
            '%zone_name%' => $zoneName,
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

            // Use trim() to remove extra space at then because of empty %value%
            return trim($this->translator->trans('pricing.rule.humanizer.diff', [
                '%task_type%' => $taskType,
                '%operator%' => $value,
                '%value%' => '',
            ]));

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
                return $this->humanizePackagesTotalVolumeUnits($node->attributes['operator'], $node->nodes['right']);
            }

        } else {
            $attributeName = 'unknown';
        }

        if ($node->attributes['operator'] === 'in') {

            $left = $node->nodes['right']->nodes['left']->attributes['value'];
            $right = $node->nodes['right']->nodes['right']->attributes['value'];

            return $this->translator->trans('pricing.rule.humanizer.between', [
                '%left%' => $this->formatValue($left, $attributeName),
                '%right%' => $this->formatValue($right, $attributeName),
            ]);

        } else {

            $value = $node->nodes['right']->attributes['value'];

            if ($attributeName === 'task.type' && $node->attributes['operator'] === '==') {
                return $this->humanizeTaskType($value);
            } elseif ($attributeName === 'order.itemsTotal') {
                return $this->humanizeOrderItemsTotal($node->attributes['operator'], $value);
            } elseif ($node->attributes['operator'] === '<') {
                return $this->translator->trans('pricing.rule.humanizer.less_than', [
                    '%value%' => $this->formatValue($value, $attributeName),
                ]);
            } elseif ($node->attributes['operator'] === '>') {
                return $this->translator->trans('pricing.rule.humanizer.more_than', [
                    '%value%' => $this->formatValue($value, $attributeName),
                ]);
            }

            //TODO: handle other operators
            return sprintf('%s', $this->formatValue($value, $attributeName));
        }
    }

    private function formatValue($value, $attribute)
    {
        switch ($attribute) {
            case 'weight':
                return $this->translator->trans('pricing.rule.humanizer.kg_unit', [
                    '%value%' => number_format($value / 1000, 2),
                ]);
            case 'distance':
                return $this->translator->trans('pricing.rule.humanizer.km_unit', [
                    '%value%' => number_format($value / 1000, 2),
                ]);
            case 'packages.totalVolumeUnits()':
                return $this->translator->trans('pricing.rule.humanizer.volume_unit', [
                    '%value%' => number_format($value, 0),
                ]);
        }

        return $value;
    }

    private function humanizeTaskType(string $taskType): string
    {
        return $this->translator->trans('pricing.rule.humanizer.task_type_is', [
            '%value%' => $this->translateTaskTypeName($taskType),
        ]);
    }

    private function humanizePackagesTotalVolumeUnits(string $operator, BinaryNode|ConstantNode $value): string
    {
        if ('in' === $operator) {
            $translatedOperator = $this->translator->trans('pricing.rule.humanizer.between', [
                '%left%' => $this->formatValue($value->nodes['left']->attributes['value'], 'packages.totalVolumeUnits()'),
                '%right%' => $this->formatValue($value->nodes['right']->attributes['value'], 'packages.totalVolumeUnits()'),
            ]);
            $translatedValue = '';
        } else {
            $translatedOperator = $this->translateOperator($operator);
            $translatedValue = $value->attributes['value'];
        }

        // Use trim() to remove extra space at then because of empty %value%
        return trim($this->translator->trans('pricing.rule.humanizer.packages_volume_units', [
            '%operator%' => $translatedOperator,
            '%value%' => $translatedValue,
        ]));
    }

    private function humanizeOrderItemsTotal(string $operator, $value): string
    {
        $translatedOperator = $this->translateOperator($operator);

        return $this->translator->trans('pricing.rule.humanizer.order_items_total', [
            '%operator%' => $translatedOperator,
            '%value%' => $value,
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

    private function humanizePriceExpression(PriceExpression $priceExpression, string $rawPriceExpression): string
    {
        if ($priceExpression instanceof FixedPriceExpression) {
            return $this->humanizeFixedPriceExpression($priceExpression);
        } elseif ($priceExpression instanceof PriceRangeExpression) {
            return $this->humanizePriceRangeExpression($priceExpression, $rawPriceExpression);
        } elseif ($priceExpression instanceof PricePerPackageExpression) {
            return $this->humanizePricePerPackageExpression($priceExpression, $rawPriceExpression);
        } elseif ($priceExpression instanceof PricePercentageExpression) {
            return $this->humanizePricePercentageExpression($priceExpression);
        } else {
            return $rawPriceExpression;
        }
    }

    private function humanizeFixedPriceExpression(FixedPriceExpression $priceExpression): string
    {
        return $this->priceFormatter->formatWithSymbol($priceExpression->value);
    }

    private function humanizePriceRangeExpression(PriceRangeExpression $priceExpression, string $rawPriceExpression): string
    {
        if (in_array($priceExpression->attribute, ['distance', 'weight', 'packages.totalVolumeUnits()'])) {
            if ($priceExpression->threshold === 0) {
                return $this->translator->trans('pricing.rule.humanizer.price_range', [
                    '%unit_price%' => $this->priceFormatter->formatWithSymbol($priceExpression->price),
                    '%step%' => $this->formatValue($priceExpression->step, $priceExpression->attribute),
                ]);
            } else {
                return $this->translator->trans('pricing.rule.humanizer.price_range_with_threshold', [
                    '%unit_price%' => $this->priceFormatter->formatWithSymbol($priceExpression->price),
                    '%step%' => $this->formatValue($priceExpression->step, $priceExpression->attribute),
                    '%threshold%' => $this->formatValue($priceExpression->threshold, $priceExpression->attribute),
                ]);
            }
        } else {
            return $rawPriceExpression;
        }
    }

    private function humanizePricePerPackageExpression(PricePerPackageExpression $priceExpression, string $rawPriceExpression): string
    {
        if ($priceExpression->hasDiscount()) {
            return $rawPriceExpression;
        }

        return $this->translator->trans('pricing.rule.humanizer.price_per_package', [
            '%package_name%' => $priceExpression->packageName,
            '%unit_price%' => $this->priceFormatter->formatWithSymbol($priceExpression->unitPrice),
        ]);
    }

    private function humanizePricePercentageExpression(PricePercentageExpression $priceExpression): string
    {
        $percentage = intval(($priceExpression->percentage - PricePercentageExpression::PERCENTAGE_NEUTRAL) / 100);

        if ($percentage > 0) {
            return sprintf('+%s%%', $percentage);
        } else {
            return sprintf('%s%%', $percentage);
        }
    }
}
