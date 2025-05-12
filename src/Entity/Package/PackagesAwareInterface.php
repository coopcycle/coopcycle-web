<?php

namespace AppBundle\Entity\Package;

use AppBundle\Entity\Package;
use Doctrine\Common\Collections\ArrayCollection;

interface PackagesAwareInterface
{
    /**
     * @return ArrayCollection<PackageWithQuantityInterface>
     */
    public function getPackages();

    public function addPackageWithQuantity(Package $package, $quantity = 1);
}
