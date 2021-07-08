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

            $rest = $quantity - $offset;
            $rest = $rest < 0 ? 0 : $rest;

            $quantityWithBasePrice = min($quantity, $offset);

            return ($basePrice * $quantityWithBasePrice) + ($discountPrice * $rest);
        };

        return array(
            new ExpressionFunction('price_per_package', $compiler, $evaluator),
        );
    }
}
