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
}
