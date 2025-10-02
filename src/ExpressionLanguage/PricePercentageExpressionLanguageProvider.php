<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Address;
use AppBundle\Pricing\PriceExpressions\PricePercentageExpression;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class PricePercentageExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{

    public function getFunctions()
    {
        $compiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
        };

        $evaluator = function ($arguments, $value): int {

            if (!$value) {

                return PricePercentageExpression::PERCENTAGE_NEUTRAL; // no change
            }

            return $value;
        };

        return array(
            new ExpressionFunction('price_percentage', $compiler, $evaluator),
        );
    }
}
