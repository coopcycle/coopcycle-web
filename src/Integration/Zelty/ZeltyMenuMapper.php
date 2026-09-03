<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductOptions;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Integration\Zelty\Dto\ZeltyItem;
use AppBundle\Integration\Zelty\Dto\ZeltyMenuPart;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;

/**
 * Maps Zelty menu data to Sylius products.
 */
class ZeltyMenuMapper
{
    /**
     * Entities created during the current import, indexed by code.
     * Nothing is flushed until the whole catalog has been mapped, so a
     * database lookup can't see them yet.
     *
     * @var array<string, ProductOption>
     */
    private array $optionsByCode = [];

    /**
     * @var array<string, ProductOptionValue>
     */
    private array $optionValuesByCode = [];

    public function __construct(
        private ProductFactoryInterface $productFactory,
        private ProductVariantFactoryInterface $variantFactory,
        private EntityManagerInterface $em,
        private SlugifyInterface $slugify,
    ) {}

    /**
     * Import multiple menus.
     *
     * @param array $menus Array of menu items
     * @param array $menuPartsMap Map of menu part IDs to menu parts
     * @param array $productsMap Map of product IDs to products
     * @param LocalBusiness $restaurant The restaurant
     * @param string $locale The locale code
     * @param array<string,TaxCategory> $taxesMap Map of tax IDs to tax categories
     * @param TaxCategory|null $defaultTaxCategory Fallback tax category
     * @return array Map of menu ID to menu Product
     */
    public function importMenus(
        array $menus,
        array $menuPartsMap,
        array $productsMap,
        array $optionsMap,
        LocalBusiness $restaurant,
        string $locale,
        array $taxesMap = [],
        ?TaxCategory $defaultTaxCategory = null
    ): array {
        $menuProductMap = [];
        $this->optionsByCode = [];
        $this->optionValuesByCode = [];

        foreach ($menus as $menu) {
            $menuProduct = $this->importMenu($menu, $menuPartsMap, $productsMap, $optionsMap, $restaurant, $locale, $taxesMap, $defaultTaxCategory);
            $menuProductMap[$menu->id] = $menuProduct;
        }

        return $menuProductMap;
    }

    /**
     * Import a single menu as a product.
     */
    private function importMenu(
        ZeltyItem $menu,
        array $menuPartsMap,
        array $productsMap,
        array $optionsMap,
        LocalBusiness $restaurant,
        string $locale,
        array $taxesMap,
        ?TaxCategory $defaultTaxCategory = null
    ): Product {
        $product = $this->findExistingMenuProduct($menu->id);

        if ($product === null) {
            $product = $this->createMenuProduct($menu, $restaurant, $locale);
        }

        $this->updateProductDetails($product, $menu);
        $this->importMenuVariant($product, $menu, $taxesMap, $defaultTaxCategory);
        $this->importMenuPartsAsOptions($product, $menu, $menuPartsMap, $productsMap, $optionsMap, $restaurant, $locale);

        $this->em->persist($product);

        return $product;
    }

    /**
     * Find an existing menu product by code.
     */
    private function findExistingMenuProduct(string $menuId): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy([
            'code' => $menuId,
        ]);
    }


    /**
     * Create a new menu product.
     */
    private function createMenuProduct(ZeltyItem $menu, LocalBusiness $restaurant, string $locale): Product
    {
        /** @var Product $product */
        $product = $this->productFactory->createNew();
        $product->setCode($menu->id);
        $product->setRestaurant($restaurant);
        $product->setSlug($this->generateMenuSlug($menu));
        $product->setCurrentLocale($locale);
        $product->setEnabled(!$menu->disabled);

        $this->em->persist($product);

        return $product;
    }

    /**
     * Generate a slug for a menu product.
     */
    private function generateMenuSlug(ZeltyItem $menu): string
    {
        $name = $menu->name ?? $menu->id;
        return $this->slugify->slugify($name . '-' . $menu->id);
    }


    /**
     * Update product name, description and enabled status.
     */
    private function updateProductDetails(Product $product, ZeltyItem $menu): void
    {
        if ($menu->name) {
            $product->setName($menu->name);
        }

        if ($menu->description) {
            $product->setDescription($menu->description);
        }

        $product->setEnabled(!$menu->disabled);
        $product->setZeltyId($menu->id);
        $product->setZeltyInternalId($menu->internalId);
    }

    /**
     * Import or update the menu variant with pricing and tax category.
     */
    private function importMenuVariant(
        Product $product,
        ZeltyItem $menu,
        array $taxesMap,
        ?TaxCategory $defaultTaxCategory = null
    ): void {
        $price = $menu->price?->price ?? 0;
        $taxCategory = $this->resolveTaxCategory($menu, $taxesMap, $defaultTaxCategory);
        $variant = $product->getVariants()->first() ?: null;

        if ($variant === null) {
            $variant = $this->createMenuVariant($product, $menu->id, $price, $taxCategory);
        } else {
            $variant->setPrice($price);
        }

        // Order pages and the confirmation e-mail print the variant name, not the
        // product's — see ZeltyProductMapper::importProductVariant().
        if ($product->getName()) {
            $variant->setName($product->getName());
        }

        // Same reasoning as ZeltyProductMapper::importProductVariant(): re-apply on
        // every import so a variant created before the tax-mapping fix gets corrected.
        if ($taxCategory !== null) {
            $variant->setTaxCategory($taxCategory);
        }
    }

    /**
     * Resolve the appropriate tax category for a menu, same rule as
     * ZeltyProductMapper::resolveTaxCategory() for a dish.
     */
    private function resolveTaxCategory(
        ZeltyItem $menu,
        array $taxesMap,
        ?TaxCategory $defaultTaxCategory
    ): ?TaxCategory {
        if ($menu->taxRule?->taxId && isset($taxesMap[$menu->taxRule->taxId])) {
            return $taxesMap[$menu->taxRule->taxId];
        }

        return $defaultTaxCategory;
    }

    /**
     * Create a new menu variant.
     */
    private function createMenuVariant(
        Product $product,
        string $menuId,
        int $price,
        ?TaxCategory $taxCategory
    ): ProductVariant {
        /** @var ProductVariant $variant */
        $variant = $this->variantFactory->createForProduct($product);
        $variant->setCode(sprintf('%s_variant', $menuId));
        $variant->setPrice($price);

        if ($taxCategory !== null) {
            $variant->setTaxCategory($taxCategory);
        }

        $product->addVariant($variant);
        $this->em->persist($variant);

        return $variant;
    }

    /**
     * Import menu parts as product options.
     */
    private function importMenuPartsAsOptions(
        Product $menuProduct,
        ZeltyItem $menu,
        array $menuPartsMap,
        array $productsMap,
        array $optionsMap,
        LocalBusiness $restaurant,
        string $locale
    ): void {
        $existingOptions = $this->indexOptionsByCode($menuProduct);

        foreach ($menu->parts as $partId) {
            if (!isset($menuPartsMap[$partId])) {
                continue;
            }

            $part = $menuPartsMap[$partId];
            $option = $this->getOrCreateMenuPartOption($part, $partId, $existingOptions, $restaurant, $locale);
            $this->linkOptionToProduct($menuProduct, $option);
            $partOptionValues = $this->importPartOptionValues($option, $part, $menu, $productsMap, $locale);

            foreach ($part->dishIds as $dishId) {
                if (!isset($productsMap[$dishId])) {
                    continue;
                }
                $dishProduct = $productsMap[$dishId];
                $partOptionValue = $partOptionValues[$dishId] ?? null;

                foreach ($dishProduct->getOptions() as $dishOption) {
                    $this->linkOptionToProduct($menuProduct, $dishOption, false);

                    if ($partOptionValue !== null) {
                        foreach ($dishOption->getValues() as $dishOptionValue) {
                            if (!$dishOptionValue->getDependsOn()->contains($partOptionValue)) {
                                $dishOptionValue->getDependsOn()->add($partOptionValue);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Index existing options by their code.
     */
    private function indexOptionsByCode(Product $menuProduct): array
    {
        $existingOptions = [];
        foreach ($menuProduct->getOptions() as $option) {
            $existingOptions[$option->getCode()] = $option;
        }
        return $existingOptions;
    }

    /**
     * Get or create a product option for a menu part.
     */
    private function getOrCreateMenuPartOption(
        ZeltyMenuPart $part,
        string $partId,
        array $existingOptions,
        LocalBusiness $restaurant,
        string $locale
    ): ProductOption {
        $optionCode = $partId;

        if (isset($existingOptions[$optionCode])) {
            return $existingOptions[$optionCode];
        }

        // A menu part can be shared by several menus of the same catalog.
        if (isset($this->optionsByCode[$optionCode])) {
            return $this->optionsByCode[$optionCode];
        }

        $option = $this->em->getRepository(ProductOption::class)->findOneBy([
            'code' => $optionCode,
        ]);

        if ($option === null) {
            $option = $this->createMenuPartOption($part, $partId, $restaurant, $locale);
        }

        $this->optionsByCode[$optionCode] = $option;

        return $option;
    }

    /**
     * Create a new product option for a menu part.
     */
    private function createMenuPartOption(
        ZeltyMenuPart $part,
        string $partId,
        LocalBusiness $restaurant,
        string $locale
    ): ProductOption {
        /** @var ProductOption $option */
        $option = new ProductOption();
        $option->setCode($partId);
        $option->setPosition(0);
        $option->setRestaurant($restaurant);
        $option->setCurrentLocale($locale);

        if ($part->name) {
            $option->setName($part->name);
        }

        $this->em->persist($option);

        return $option;
    }

    /**
     * Link a product option to a menu product if not already linked.
     */
    private function linkOptionToProduct(Product $menuProduct, ProductOption $option, bool $enabled = true): void
    {
        $productId = $menuProduct->getId();
        $optionId = $option->getId();

        if ($productId !== null && $optionId !== null) {
            // The global DisabledFilter adds "enabled = true" to all ProductOptions queries.
            // Dish options linked to menus use enabled=false (hidden from menu display), so the
            // filter would hide them — causing hasOption() and findOneBy() to both miss the
            // existing record on re-push, and addOption() to try inserting a duplicate row.
            $existing = $this->withoutDisabledFilter(fn () =>
                $this->em->getRepository(ProductOptions::class)->findOneBy([
                    'product' => $productId,
                    'option'  => $optionId,
                ])
            );

            if ($existing !== null) {
                $existing->setEnabled($enabled);
                return;
            }
        }

        $menuProduct->addOption($option);

        if (!$enabled) {
            foreach ($menuProduct->getProductOptions() as $po) {
                if ($po->getOption() === $option) {
                    $po->setEnabled(false);
                    break;
                }
            }
        }
    }

    /**
     * Import option values for a menu part.
     */
    private function importPartOptionValues(
        ProductOption $option,
        ZeltyMenuPart $part,
        ZeltyItem $menu,
        array $productsMap,
        string $locale
    ): array {
        $existingValues = $this->indexOptionValuesByCode($option);
        $partOptionValues = [];

        foreach ($part->dishIds as $position => $dishId) {
            $valueCode = sprintf('%s_%s', $part->id, $dishId);
            $value = $this->getOrCreatePartOptionValue($valueCode, $dishId, $existingValues, $productsMap, $menu, $locale);

            if (!$option->getValues()->contains($value)) {
                $option->addValue($value);
            }
            $partOptionValues[$dishId] = $value;
        }

        return $partOptionValues;
    }

    /**
     * Index option values by their code.
     */
    private function indexOptionValuesByCode(ProductOption $option): array
    {
        $existingValues = [];
        foreach ($option->getValues() as $value) {
            $existingValues[$value->getCode()] = $value;
        }
        return $existingValues;
    }

    /**
     * Get or create an option value for a part's dish.
     */
    private function getOrCreatePartOptionValue(
        string $valueCode,
        string $dishId,
        array $existingValues,
        array $productsMap,
        ZeltyItem $menu,
        string $locale
    ): ProductOptionValue {
        if (isset($existingValues[$valueCode])) {
            $this->updateExistingOptionValueMetadata($existingValues[$valueCode], $dishId, $productsMap);
            return $existingValues[$valueCode];
        }

        return $this->createPartOptionValue($valueCode, $dishId, $productsMap, $menu, $locale);
    }

    /**
     * Update metadata on an existing option value.
     */
    private function updateExistingOptionValueMetadata(
        ProductOptionValue $value,
        string $dishId,
        array $productsMap
    ): void {
        $value->setZeltyId($dishId);
        $value->setZeltyInternalId(isset($productsMap[$dishId]) ? $productsMap[$dishId]->getZeltyInternalId() : null);
    }

    /**
     * Create a new option value for a part's dish.
     *
     * @return ProductOptionValue The created or existing option value
     */
    private function createPartOptionValue(
        string $valueCode,
        string $dishId,
        array $productsMap,
        ZeltyItem $menu,
        string $locale
    ): ProductOptionValue {
        if (isset($this->optionValuesByCode[$valueCode])) {
            return $this->optionValuesByCode[$valueCode];
        }

        // Values belonging to a disabled menu are stored with enabled=false, and the
        // global DisabledFilter would hide them here, leading to a duplicate insert.
        /** @var ProductOptionValue|null $value */
        $value = $this->withoutDisabledFilter(fn () =>
            $this->em->getRepository(ProductOptionValue::class)->findOneBy([
                'code' => $valueCode,
            ])
        );

        if ($value === null) {
            $value = new ProductOptionValue();
            $value->setCode($valueCode);
            $value->setZeltyId($dishId);
            $value->setZeltyInternalId(isset($productsMap[$dishId]) ? $productsMap[$dishId]->getZeltyInternalId() : null);
            $value->setCurrentLocale($locale);

            [$dishName] = $this->extractDishInfo($dishId, $productsMap);
            $value->setValue($dishName ?? $dishId);
            $value->setEnabled(!$menu->disabled);

            $this->em->persist($value);
        }

        $this->optionValuesByCode[$valueCode] = $value;

        return $value;
    }

    /**
     * Extract dish name and metadata from products map.
     *
     * @return array{string|null, array<string, string>} Tuple of [dishName, metadata]
     */
    private function extractDishInfo(string $dishId, array $productsMap): array
    {
        if (!isset($productsMap[$dishId])) {
            return [null, []];
        }

        $dishProduct = $productsMap[$dishId];
        $metadata = [
            'dish_id' => $dishId,
            'dish_code' => $dishProduct->getCode(),
        ];

        return [$dishProduct->getName(), $metadata];
    }

    /**
     * Run a lookup with the global DisabledFilter turned off, so rows with
     * enabled=false are visible.
     */
    private function withoutDisabledFilter(callable $callback)
    {
        $filters = $this->em->getFilters();
        $filterActive = $filters->isEnabled('disabled_filter');

        if ($filterActive) {
            $filters->disable('disabled_filter');
        }

        try {
            return $callback();
        } finally {
            if ($filterActive) {
                $filters->enable('disabled_filter');
            }
        }
    }
}
