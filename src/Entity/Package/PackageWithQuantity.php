<?php

namespace AppBundle\Entity\Package;

use AppBundle\Entity\Package;

class PackageWithQuantity implements PackageWithQuantityInterface
{
    private $package;
    private $quantity = 0;

    public function __construct(?Package $package = null, $quantity = 0)
    {
        $this->package = $package;
        $this->quantity = $quantity;
    }

    public function getPackage(): ?Package
    {
        return $this->package;
    }

    /**
     * @param mixed $package
     *
     * @return self
     */
    public function setPackage($package)
    {
        $this->package = $package;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param mixed $quantity
     *
     * @return self
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }
}
