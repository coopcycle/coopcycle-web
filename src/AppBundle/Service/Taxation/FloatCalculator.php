<?php

declare(strict_types=1);

namespace AppBundle\Service\Taxation;

use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;

final class FloatCalculator implements CalculatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function calculate(float $base, TaxRateInterface $rate): float
    {
        if ($rate->isIncludedInPrice()) {
            return $this->numberFormat($base - ($base / (1 + $rate->getAmount())));
        }

        return $this->numberFormat($base * $rate->getAmount());
    }

    private function numberFormat(float $amount)
    {
        // TODO Do not cast as float
        return (float) number_format($amount, 2, '.', '');
    }
}
