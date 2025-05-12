<?php

namespace AppBundle\Entity\Package;

use AppBundle\Entity\Package;

interface PackageWithQuantityInterface
{
    public function getPackage(): Package;

    public function getQuantity(): int;
}
