<?php

namespace AppBundle\Sylius\Product;

use Ramsey\Uuid\Uuid;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

class LazyProductVariantResolver implements LazyProductVariantResolverInterface
{
    private $variantResolver;
    private $variantFactory;

    public function __construct(ProductVariantResolverInterface $variantResolver, ProductVariantFactoryInterface $variantFactory)
    {
        $this->variantResolver = $variantResolver;
        $this->variantFactory = $variantFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getVariant(ProductInterface $product): ?ProductVariantInterface
    {
        return $this->variantResolver->getVariant($product);
    }

    /**
     * {@inheritdoc}
     */
    public function getVariantForOptionValues(ProductInterface $product, array $optionValues): ?ProductVariantInterface
    {
        $mandatoryOptionValues = $this->filterOptionValues($optionValues);

        foreach ($product->getVariants() as $variant) {

            $variantOptionValues = $variant->getOptionValues()->toArray();

            if (count($this->filterOptionValues($variantOptionValues)) !== count($mandatoryOptionValues)) {
                continue;
            }

            if ($this->matchOptions($variant, $mandatoryOptionValues)) {
                return $variant;
            }
        }

        // No variant found
        $variant = $this->variantFactory->createForProduct($product);
        $values = [];
        foreach ($mandatoryOptionValues as $optionValue) {
            $variant->addOptionValue($optionValue);
        }

        $variant->setName($product->getName());
        $variant->setCode(Uuid::uuid4()->toString());

        $defaultVariant = $this->variantResolver->getVariant($product);

        // Copy price & tax category from default variant
        $variant->setPrice($defaultVariant->getPrice());
        $variant->setTaxCategory($defaultVariant->getTaxCategory());

        return $variant;
    }

    private function filterOptionValues(array $optionValues)
    {
        return array_filter($optionValues, function (ProductOptionValueInterface $optionValue) {
            return !$optionValue->getOption()->isAdditional();
        });
    }

    private function matchOptions(ProductVariantInterface $variant, array $optionValues)
    {
        foreach ($optionValues as $optionValue) {
            if (!$variant->hasOptionValue($optionValue)) {
                return false;
            }
        }

        return true;
    }
}
