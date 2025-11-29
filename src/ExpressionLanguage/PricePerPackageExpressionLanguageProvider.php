<?php

namespace AppBundle\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class PricePerPackageExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        $compiler = function () {
            // FIXME Need to test compilation
        };

        $evaluator = function ($arguments, $packages, $packageName, $basePrice, $offset, $discountPrice): PriceEvaluation|array {

            $quantity = $packages->quantity($packageName);

            // Means no discount
            if (0 === $offset) {

                return new PriceEvaluation($basePrice, $quantity);
            }

            $rest = $quantity - ($offset - 1);

            // No packages above the threshold
            if ($rest <= 0) {
                return new PriceEvaluation($basePrice, $quantity);
            }

            $quantityWithBasePrice = min($quantity, ($offset - 1));

            return [
                new PriceEvaluation($basePrice, $quantityWithBasePrice),
                new PriceEvaluation($discountPrice, $rest),
            ];
        };

        return array(
            new ExpressionFunction('price_per_package', $compiler, $evaluator),
        );
    }
}
