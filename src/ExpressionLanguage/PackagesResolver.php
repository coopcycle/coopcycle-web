<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Delivery;

class PackagesResolver
{
    private $delivery;

    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }

    public function quantity($name)
    {
        foreach ($this->delivery->getPackages() as $package) {
            if ($package->getPackage()->getName() === $name) {
                return $this->delivery->getQuantityForPackage($package->getPackage());
            }
        }

        return 0;
    }

    public function containsAtLeastOne($name): bool
    {
        foreach ($this->delivery->getPackages() as $package) {
            if ($package->getPackage()->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function totalVolumeUnits()
    {
        $total = 0;

        foreach ($this->delivery->getPackages() as $package) {
            $total += ($package->getPackage()->getVolumeUnits() * $this->delivery->getQuantityForPackage($package->getPackage()));
        }

        return $total;
    }
}
