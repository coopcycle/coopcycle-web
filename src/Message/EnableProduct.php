<?php

namespace AppBundle\Message;

use AppBundle\Entity\Sylius\Product;

class EnableProduct
{
    public $id;

    public function __construct(Product $product)
    {
        $this->id = $product->getId();
    }
}

