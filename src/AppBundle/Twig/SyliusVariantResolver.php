<?php

namespace AppBundle\Twig;

use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

class SyliusVariantResolver
{
    private $productVariantResolver;

    public function __construct(ProductVariantResolverInterface $productVariantResolver)
    {
        $this->productVariantResolver = $productVariantResolver;
    }

    public function resolveVariant(ProductInterface $product): ?ProductVariantInterface
    {
        return $this->productVariantResolver->getVariant($product);
    }
}
