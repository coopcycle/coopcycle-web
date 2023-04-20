<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Quote;

class PackagesQuoteResolver
{
    private $quote;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
    }

    public function quantity($name)
    {
        foreach ($this->quote->getPackages() as $package) {
            if ($package->getPackage()->getName() === $name) {
                return $this->quote->getQuantityForPackage($package->getPackage());
            }
        }

        return 0;
    }

    public function containsAtLeastOne($name): bool
    {
        foreach ($this->quote->getPackages() as $package) {
            if ($package->getPackage()->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function totalVolumeUnits()
    {
        $total = 0;

        foreach ($this->quote->getPackages() as $package) {
            $total += ($package->getPackage()->getVolumeUnits() * $this->quote->getQuantityForPackage($package->getPackage()));
        }

        return $total;
    }
}
