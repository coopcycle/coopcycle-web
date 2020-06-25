<?php

namespace AppBundle\Entity\LocalBusiness;

use Sylius\Component\Product\Model\ProductInterface;

interface CatalogInterface
{
    public function getProducts();

    public function hasProduct(ProductInterface $product);

    public function addProduct(ProductInterface $product);

    public function removeProduct(ProductInterface $product);
}
