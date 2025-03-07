<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Address;
use Carbon\Carbon;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class PriceRangeExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        $compiler = function (Address $address, $zoneName) {
            // FIXME Need to test compilation
        };

        $evaluator = function ($arguments, $value, $price, $step, $threshold): int {

            if (!$value) {

                return 0;
            }

            if ($value < $threshold) {

                return 0;
            }

            return (int) ceil(($value - $threshold) / $step) * $price;
        };

        return array(
            new ExpressionFunction('price_range', $compiler, $evaluator),
        );
    }
}
