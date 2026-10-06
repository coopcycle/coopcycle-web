<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Address;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class PriceRangeExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        $compiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
        };

        /**
         * The multiplier charges the range once per unit of something else: with
         * packages.totalVolumeUnits(), "0.60 € per started km beyond 2.5 km, per
         * package" is a single rule rather than one rule per package count.
         *
         * It multiplies the quantity, never the unit price: a product option value
         * is reconciled against the unit price the rule returns
         * (@see OnDemandDeliveryProductProcessor::processPriceRangeExpression), so a
         * unit price that moved with the packages would be rewritten on every order.
         */
        $evaluator = function ($arguments, $value, $price, $step, $threshold, $multiplier = 1): int|PriceEvaluation {

            if (!$value) {

                return 0;
            }

            if ($value < $threshold) {

                return 0;
            }

            if ($multiplier <= 0) {

                return 0;
            }

            $units = (int) ceil(($value - $threshold) / $step);

            return new PriceEvaluation($price, $units * (int) $multiplier);
        };

        return array(
            new ExpressionFunction('price_range', $compiler, $evaluator),
        );
    }
}
