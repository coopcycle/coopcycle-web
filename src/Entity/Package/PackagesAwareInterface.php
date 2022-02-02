<?php

namespace AppBundle\Entity\Package;

use AppBundle\Entity\Package;

interface PackagesAwareInterface
{
    public function getPackages();

    public function addPackageWithQuantity(Package $package, $quantity = 1);
}
