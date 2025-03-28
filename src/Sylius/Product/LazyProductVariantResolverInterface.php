<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\BusinessRestaurantGroup;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface as BaseProductVariantResolverInterface;

interface LazyProductVariantResolverInterface extends BaseProductVariantResolverInterface
{
    
    public function getVariantForOptionValues(ProductInterface $product, \Traversable $optionValues): ?ProductVariantInterface;

    
    public function getVariantForBusinessRestaurantGroup(ProductInterface $product, BusinessRestaurantGroup $businessRestaurantGroup): ?ProductVariantInterface;
}
