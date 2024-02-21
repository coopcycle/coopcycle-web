<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Business\Context as BusinessContext;
use AppBundle\Entity\BusinessRestaurantGroup;
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

    public function __construct(
        ProductVariantResolverInterface $variantResolver,
        ProductVariantFactoryInterface $variantFactory,
        BusinessContext $businessContext)
    {
        $this->variantResolver = $variantResolver;
        $this->variantFactory = $variantFactory;
        $this->businessContext = $businessContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getVariant(ProductInterface $product): ?ProductVariantInterface
    {
        if ($this->businessContext->isActive()) {
            $businessAccount = $this->businessContext->getBusinessAccount();
            if ($businessAccount) {
                $restaurantGroup = $businessAccount->getBusinessRestaurantGroup();

                $variant = $this->getVariantForBusinessRestaurantGroup($product, $restaurantGroup);
                if ($variant) {
                    return $variant;
                }
            }
        }

        return $this->variantResolver->getVariant($product);
    }

    /**
     * {@inheritdoc}
     */
    public function getVariantForOptionValues(ProductInterface $product, \Traversable $optionValues): ?ProductVariantInterface
    {
        foreach ($product->getVariants() as $variant) {

            if (count($variant->getOptionValues()) !== count($optionValues)) {
                continue;
            }

            if ($variant->isBusiness() !== $this->businessContext->isActive()) {
                continue;
            }

            if ($this->matchOptions($variant, $optionValues)) {
                return $variant;
            }
        }

        // No variant found
        $variant = $this->variantFactory->createForProduct($product);
        $values = [];
        foreach ($optionValues as $optionValue) {

            $quantity = null;
            if ($optionValues instanceof \SplObjectStorage) {
                $quantity = $optionValues->getInfo();
            }

            if (null !== $quantity) {
                $variant->addOptionValueWithQuantity($optionValue, (int) $quantity);
            } else {
                $variant->addOptionValue($optionValue);
            }
        }

        $variant->setName($product->getName());
        $variant->setCode(Uuid::uuid4()->toString());

        $defaultVariant = $this->getVariant($product);

        // Copy price & tax category from default variant
        $variant->setPrice($defaultVariant->getPrice());
        $variant->setTaxCategory($defaultVariant->getTaxCategory());

        return $variant;
    }

    private function matchOptions(ProductVariantInterface $variant, \Traversable $optionValues)
    {
        foreach ($optionValues as $optionValue) {

            $quantity = null;
            if ($optionValues instanceof \SplObjectStorage) {
                $quantity = $optionValues->getInfo();
            }

            if (null !== $quantity) {
                if (!$variant->hasOptionValueWithQuantity($optionValue, (int) $quantity)) {
                    return false;
                }
            } else {
                if (!$variant->hasOptionValue($optionValue)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getVariantForBusinessRestaurantGroup(ProductInterface $product, BusinessRestaurantGroup $businessRestaurantGroup): ?ProductVariantInterface
    {
        foreach ($product->getVariants() as $variant) {
            $variantBusinessRestaurantGroup = $variant->getBusinessRestaurantGroup();

            if (null === $variantBusinessRestaurantGroup) {
                continue;
            }

            if ($businessRestaurantGroup === $variantBusinessRestaurantGroup) {
                return $variant;
            }
        }

        return null;
    }
}
