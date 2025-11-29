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

        $evaluator = function ($arguments, $value, $price, $step, $threshold): int|PriceEvaluation {

            if (!$value) {

                return 0;
            }

            if ($value < $threshold) {

                return 0;
            }

            return new PriceEvaluation($price, (int) ceil(($value - $threshold) / $step));
        };

        return array(
            new ExpressionFunction('price_range', $compiler, $evaluator),
        );
    }
}
