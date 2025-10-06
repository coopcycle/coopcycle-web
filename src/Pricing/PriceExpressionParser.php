<?php

namespace AppBundle\Pricing;

use AppBundle\ExpressionLanguage\ExpressionLanguage;
use AppBundle\Pricing\PriceExpressions\FixedPriceExpression;
use AppBundle\Pricing\PriceExpressions\PricePercentageExpression;
use AppBundle\Pricing\PriceExpressions\PriceExpression;
use AppBundle\Pricing\PriceExpressions\PricePerPackageExpression;
use AppBundle\Pricing\PriceExpressions\PriceRangeExpression;
use AppBundle\Pricing\PriceExpressions\UnparsablePriceExpression;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\FunctionNode;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

/**
 * Helper class to parse price expressions and convert them to PHP price objects
 *
 * $fixedPrice = $parser->parsePrice('1053'); // Returns FixedPrice
 * $percentagePrice = $parser->parsePrice('price_percentage(8500)'); // Returns PercentagePrice
 * $rangePrice = $parser->parsePrice('price_range(distance, 450, 2000, 2500)'); // Returns PriceRange
 * $packagePrice = $parser->parsePrice('price_per_package(packages, "XXL", 1240, 3, 210)'); // Returns PricePerPackage
 */
class PriceExpressionParser
{
    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage
    ) {
    }

    public function parsePrice(string $expression): PriceExpression
    {
        $parsedExpression = $this->expressionLanguage->parsePrice($expression);

        if (null === $parsedExpression) {
            return new UnparsablePriceExpression($expression);
        }

        return $this->parsePriceNode($parsedExpression->getNodes(), $expression);
    }

    private function parsePriceNode(Node $node, string $expression): PriceExpression
    {
        // Handle price_range function
        if ($node instanceof FunctionNode && $node->attributes['name'] === 'price_range') {
            return $this->parsePriceRangeFunction($node);
        }

        // Handle price_percentage function
        if ($node instanceof FunctionNode && $node->attributes['name'] === 'price_percentage') {
            return $this->parsePricePercentageFunction($node);
        }

        // Handle price_per_package function
        if ($node instanceof FunctionNode && $node->attributes['name'] === 'price_per_package') {
            return $this->parsePricePerPackageFunction($node);
        }

        // Handle fixed price (constant value)
        if ($node instanceof ConstantNode && is_numeric($node->attributes['value'])) {
            return new FixedPriceExpression((int) $node->attributes['value']);
        }

        // If we can't parse it into a specific type, return raw expression
        return new UnparsablePriceExpression($expression);
    }

    /**
     * Parse price_range function call
     */
    private function parsePriceRangeFunction(FunctionNode $node): PriceRangeExpression
    {
        $args = $node->nodes['arguments']->nodes;

        // Extract attribute name
        $attribute = $this->extractAttributeName($args[0]);

        // Extract other parameters
        $price = (int) $args[1]->attributes['value'];
        $step = (int) $args[2]->attributes['value'];
        $threshold = (int) $args[3]->attributes['value'];

        return new PriceRangeExpression($attribute, $price, $step, $threshold);
    }

    /**
     * Parse price_percentage function call
     */
    private function parsePricePercentageFunction(FunctionNode $node): PricePercentageExpression
    {
        $args = $node->nodes['arguments']->nodes;
        $percentage = (int) $args[0]->attributes['value'];

        return new PricePercentageExpression($percentage);
    }

    /**
     * Parse price_per_package function call
     */
    private function parsePricePerPackageFunction(FunctionNode $node): PricePerPackageExpression
    {
        $args = $node->nodes['arguments']->nodes;

        $packageName = $args[1]->attributes['value'];
        $unitPrice = (int) $args[2]->attributes['value'];
        $offset = (int) $args[3]->attributes['value'];
        $discountPrice = (int) $args[4]->attributes['value'];

        return new PricePerPackageExpression($packageName, $unitPrice, $offset, $discountPrice);
    }

    /**
     * Extract attribute name from a node, handling both simple names and method calls
     */
    private function extractAttributeName(Node $node): string
    {
        // Handle simple attribute names like 'distance'
        if (isset($node->attributes['name'])) {
            return $node->attributes['name'];
        }

        // Handle method calls like packages.totalVolumeUnits()
        if ($node instanceof GetAttrNode &&
            isset($node->nodes['node']->attributes['name']) &&
            isset($node->nodes['attribute']->attributes['value'])) {

            $objectName = $node->nodes['node']->attributes['name'];
            $methodName = $node->nodes['attribute']->attributes['value'];

            // Check if it's a method call (type: 2)
            if (isset($node->attributes['type']) && $node->attributes['type'] === 2) {
                return $objectName . '.' . $methodName . '()';
            } else {
                return $objectName . '.' . $methodName;
            }
        }

        return 'unknown';
    }
}
