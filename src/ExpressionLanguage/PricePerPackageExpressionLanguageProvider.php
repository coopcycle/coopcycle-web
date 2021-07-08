<?php

namespace AppBundle\ExpressionLanguage;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class PricePerPackageExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        $compiler = function () {
            // FIXME Need to test compilation
        };

        $evaluator = function ($arguments, $packages, $packageName, $basePrice, $offset, $discountPrice) {

            $quantity = $packages->quantity($packageName);

            // Means no discount
            if (0 === $offset) {

                return $basePrice * $quantity;
            }

            $rest = $quantity - ($offset - 1);
            $rest = $rest < 0 ? 0 : $rest;

            $quantityWithBasePrice = min($quantity, ($offset - 1));

            return ($basePrice * $quantityWithBasePrice) + ($discountPrice * $rest);
        };

        return array(
            new ExpressionFunction('price_per_package', $compiler, $evaluator),
        );
    }
}
