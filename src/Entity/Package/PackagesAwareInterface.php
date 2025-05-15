<?php

namespace AppBundle\Entity\Package;

use AppBundle\Entity\Package;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @template T of PackageWithQuantityInterface
 */
interface PackagesAwareInterface
{
    /**
     * @return ArrayCollection<int, T>
     */
    public function getPackages();

    public function addPackageWithQuantity(Package $package, $quantity = 1);
}
