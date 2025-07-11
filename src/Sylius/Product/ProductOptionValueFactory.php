<?php

namespace AppBundle\Sylius\Product;

use Sylius\Resource\Factory\FactoryInterface;

class ProductOptionValueFactory
{
    public function __construct(
        private readonly FactoryInterface $decorated
    ) {
    }

    public function createNew()
    {
        return $this->decorated->createNew();
    }
}
