<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOptions;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Integration\Zelty\Dto\ZeltyItem;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;

/**
 * Maps Zelty dishes/items to Sylius products.
 */
class ZeltyProductMapper
{
    public function __construct(
        private ProductFactoryInterface $productFactory,
        private ProductVariantFactoryInterface $variantFactory,
        private EntityManagerInterface $em,
        private SlugifyInterface $slugify,
    ) {}

    /**
     * Import multiple dishes.
     *
     * @param array<ZeltyItem> $dishes Array of ZeltyItem objects
     * @param LocalBusiness $restaurant The restaurant
     * @param $optionsMap<int, ProductOptionInterface> Map of option IDs to options
     * @param string $locale The locale code
     * @param array<string,TaxCategory> $taxesMap Map of tax IDs to tax categories
     * @param TaxCategory|null $defaultTaxCategory Fallback tax category
     * @return array Map of product codes to Product entities
     */
    public function importDishes(
        array $dishes,
        LocalBusiness $restaurant,
        array $optionsMap,
        string $locale,
        array $taxesMap,
        ?TaxCategory $defaultTaxCategory = null
    ): array {
        $productMap = [];

        foreach ($dishes as $dish) {
            $product = $this->importDish($dish, $restaurant, $optionsMap, $locale, $taxesMap, $defaultTaxCategory);
            $productMap[$product->getCode()] = $product;
        }

        return $productMap;
    }

    /**
     * Get a product by its code.
     */
    public function getProductByCode(string $code): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['code' => $code]);
    }

    /**
     * Import a single dish as a product.
     */
    private function importDish(
        ZeltyItem $dish,
        LocalBusiness $restaurant,
        array $optionsMap,
        string $locale,
        array $taxesMap,
        ?TaxCategory $defaultTaxCategory = null
    ): Product {
        $product = $this->findExistingProduct($dish->id);


        if ($product === null) {
            $product = $this->createDishProduct($dish, $restaurant, $locale);
        }

        $this->updateProductDetails($product, $dish);
        $this->importProductVariant($product, $dish, $taxesMap, $defaultTaxCategory);

        $this->em->persist($product);
        $this->linkOptions($product, $dish, $optionsMap);

        return $product;
    }

    /**
     * Find an existing product by code.
     */
    private function findExistingProduct(string $dishId): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy([
            'code' => $dishId
        ]);
    }

    /**
     * Create a new dish product.
     */
    private function createDishProduct(ZeltyItem $dish, LocalBusiness $restaurant, string $locale): Product
    {
        /** @var Product $product */
        $product = $this->productFactory->createNew();
        $product->setCode($dish->id);
        $product->setRestaurant($restaurant);
        $product->setSlug($this->generateProductSlug($dish));
        $product->setCurrentLocale($locale);

        $this->em->persist($product);

        return $product;
    }

    /**
     * Generate a slug for a product.
     */
    private function generateProductSlug(ZeltyItem $dish): string
    {
        $name = $dish->name ?? $dish->id;
        return $this->slugify->slugify($name . '-' . $dish->id);
    }

    /**
     * Update product name, description and enabled status.
     */
    private function updateProductDetails(Product $product, ZeltyItem $dish): void
    {
        if ($dish->name) {
            $product->setName($dish->name);
        }

        if ($dish->description) {
            $product->setDescription($dish->description);
        }

        $product->setEnabled(!$dish->disabled);
        $product->setZeltyId($dish->id);
        $product->setZeltyInternalId($dish->internalId);
    }

    /**
     * Import or update the product variant with pricing and tax category.
     */
    private function importProductVariant(
        Product $product,
        ZeltyItem $dish,
        array $taxesMap,
        ?TaxCategory $defaultTaxCategory = null
    ): void {
        $price = $dish->price?->price ?? 0;
        $taxCategory = $this->resolveTaxCategory($dish, $taxesMap, $defaultTaxCategory);

        //TODO: Check if variant is the default one ?
        $variant = $this->findVariantWithPrice($product, $price)
            ?? $this->createProductVariant($product, $dish->id, $price, $taxCategory);

        // Order pages and the confirmation e-mail print the *variant* name, not the
        // product's, so a variant left unnamed shows up as a blank line. Set on every
        // import, not only on creation: variants created before this fix are still out
        // there, and nothing else would ever name them.
        if ($product->getName()) {
            $variant->setName($product->getName());
        }

        // Same reasoning for the tax category: a stale mapping bug (fixed in
        // ZeltyTaxesMapper) meant every variant used to be created with the
        // restaurant's default category regardless of the dish's own tax rule.
        // Re-apply it on every import so already-imported variants get corrected.
        if ($taxCategory !== null) {
            $variant->setTaxCategory($taxCategory);
        }
    }

    /**
     * Find a variant of this product already selling at the given price.
     */
    private function findVariantWithPrice(Product $product, int $price): ?ProductVariant
    {
        /** @var ProductVariant $existingVariant */
        foreach ($product->getVariants() as $existingVariant) {
            if ($existingVariant->getPrice() === $price) {
                return $existingVariant;
            }
        }

        return null;
    }

    /**
     * Resolve the appropriate tax category for a dish.
     */
    private function resolveTaxCategory(
        ZeltyItem $dish,
        array $taxesMap,
        ?TaxCategory $defaultTaxCategory
    ): ?TaxCategory {
        if ($dish->taxRule?->taxId && isset($taxesMap[$dish->taxRule->taxId])) {
            return $taxesMap[$dish->taxRule->taxId];
        }

        return $defaultTaxCategory;
    }

    /**
     * Create a new product variant.
     */
    private function createProductVariant(
        Product $product,
        string $dishId,
        int $price,
        ?TaxCategory $taxCategory
    ): ProductVariant {
        /** @var ProductVariant $variant */
        $variant = $this->variantFactory->createForProduct($product);
        $variant->setCode(sprintf('%s_variant', $dishId));
        $variant->setPrice($price);

        if ($taxCategory !== null) {
            $variant->setTaxCategory($taxCategory);
        }

        //TODO: Check if done right ?
        $product->addVariant($variant);
        $this->em->persist($variant);

        return $variant;
    }

    /**
     * Link product options to the product.
     */
    private function linkOptions(Product $product, ZeltyItem $dish, array $optionsMap): void
    {
        foreach ($dish->optionIds as $zeltyOptionId) {
            if (!isset($optionsMap[$zeltyOptionId])) {
                continue;
            }

            $option = $optionsMap[$zeltyOptionId];
            $this->linkOptionToProductIfNotExists($product, $option);
        }
    }

    /**
     * Link a single option to product if not already linked.
     *
     * @param ProductOptionInterface|object $option The option to link
     */
    private function linkOptionToProductIfNotExists(Product $product, $option): void
    {
        if ($product->hasOption($option)) {
            return;
        }

        $productId = $product->getId();
        $optionId = $option->getId();
        if ($productId !== null && $optionId !== null) {
            $existing = $this->em->getRepository(ProductOptions::class)->findOneBy([
                'product' => $productId,
                'option' => $optionId,
            ]);
            if ($existing !== null) {
                return;
            }
        }

        $product->addOption($option);

        foreach ($option->getValues() as $optionValue) {
            if ($optionValue->getProduct() === null) {
                $optionValue->setProduct($product);
            }
        }
    }
}
