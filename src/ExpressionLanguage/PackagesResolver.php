<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Package\PackagesAwareInterface;

class PackagesResolver
{
    private $delivery;

    public function __construct(PackagesAwareInterface $delivery)
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
            $total += ($package->getPackage()->getMaxVolumeUnits() * $this->delivery->getQuantityForPackage($package->getPackage()));
        }

        return $total;
    }
}
