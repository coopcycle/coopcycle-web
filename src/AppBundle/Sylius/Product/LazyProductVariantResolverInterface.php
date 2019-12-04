<?php

namespace AppBundle\Sylius\Product;

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
}
