<?php

namespace AppBundle\Entity\Package;

use AppBundle\Entity\Package;
use AppBundle\Entity\Task\Package as TaskPackage;

trait PackagesAwareTrait
{
    protected $packages;

    public function hasPackages()
    {
        return count($this->getPackages()) > 0;
    }

    public function addPackageWithQuantity(Package $package, $quantity = 1)
    {
        if (0 === $quantity) {
            return;
        }

        $wrappedPackage = $this->resolvePackage($package);
        $wrappedPackage->setQuantity($wrappedPackage->getQuantity() + $quantity);

        if (!$this->packages->contains($wrappedPackage)) {
            $this->packages->add($wrappedPackage);
        }
    }

    protected function resolvePackage(Package $package): TaskPackage
    {
        if ($this->hasPackage($package)) {
            foreach ($this->packages as $taskPackage) {
                if ($taskPackage->getPackage() === $package) {
                    return $taskPackage;
                }
            }
        }

        $taskPackage = new TaskPackage($this);
        $taskPackage->setPackage($package);

        return $taskPackage;
    }

    public function hasPackage(Package $package)
    {
        foreach ($this->getPackages() as $p) {
            if ($p->getPackage() === $package) {
                return true;
            }
        }

        return false;
    }

    public function getQuantityForPackage(Package $package)
    {
        foreach ($this->getPackages() as $p) {
            if ($p->getPackage() === $package) {
                return $p->getQuantity();
            }
        }

        return 0;
    }
}
