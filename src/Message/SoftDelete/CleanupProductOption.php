<?php

namespace AppBundle\Message\SoftDelete;

use AppBundle\Sylius\Product\ProductOptionInterface;

class CleanupProductOption
{
    private $productOptionId;

    public function __construct(ProductOptionInterface $productOption)
    {
        $this->productOptionId = $productOption->getId();
    }

    public function getProductOptionId()
    {
        return $this->productOptionId;
    }
}
