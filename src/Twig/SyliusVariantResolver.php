<?php

namespace AppBundle\Twig;

use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Twig\Extension\RuntimeExtensionInterface;

class SyliusVariantResolver implements RuntimeExtensionInterface
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
