<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\BusinessRestaurantGroup;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface as BaseProductVariantResolverInterface;

interface LazyProductVariantResolverInterface extends BaseProductVariantResolverInterface
{
    /**
     * @param ProductInterface $product
     * @param \Traversable $optionValues
     *
     * @return ProductVariantInterface|null
     */
    public function getVariantForOptionValues(ProductInterface $product, \Traversable $optionValues): ?ProductVariantInterface;

    /**
     * @param ProductInterface $product
     * @param BusinessRestaurantGroup $businessRestaurantGroup
     *
     * @return ProductVariantInterface|null
     */
    public function getVariantForBusinessRestaurantGroup(ProductInterface $product, BusinessRestaurantGroup $businessRestaurantGroup): ?ProductVariantInterface;
}
