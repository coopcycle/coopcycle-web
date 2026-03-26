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
use Ramsey\Uuid\Uuid;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;

/**
 * Maps Zelty menu data to Sylius products.
 */
class ZeltyMenuMapper
{
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
     * @param TaxCategory|null $defaultTaxCategory Fallback tax category
     * @return array Map of menu ID to menu Product
     */
    public function importMenus(
        array $menus,
        array $menuPartsMap,
        array $productsMap,
        LocalBusiness $restaurant,
        string $locale,
        ?TaxCategory $defaultTaxCategory = null
    ): array {
        $menuProductMap = [];

        foreach ($menus as $menu) {
            $menuProduct = $this->importMenu($menu, $menuPartsMap, $productsMap, $restaurant, $locale, $defaultTaxCategory);
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
        LocalBusiness $restaurant,
        string $locale,
        ?TaxCategory $defaultTaxCategory = null
    ): Product {
        $product = $this->findExistingMenuProduct($menu->id);

        if ($product === null) {
            $product = $this->createMenuProduct($menu, $restaurant, $locale);
        }

        $this->updateProductDetails($product, $menu);
        $this->importMenuVariant($product, $menu, $defaultTaxCategory);
        $this->importMenuPartsAsOptions($product, $menu, $menuPartsMap, $productsMap, $restaurant, $locale);

        $this->em->persist($product);
        $this->em->flush();

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
        $product->setCode(Uuid::uuid4()->toString());
        $product->setZeltyCode($menu->id);
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
    }

    /**
     * Import or update the menu variant with pricing.
     */
    private function importMenuVariant(Product $product, ZeltyItem $menu, ?TaxCategory $defaultTaxCategory = null): void
    {
        $price = $menu->price?->price ?? 0;
        $variant = $product->getVariants()->first() ?: null;

        if ($variant === null) {
            $variant = $this->createMenuVariant($product, $menu->id, $price, $defaultTaxCategory);
        } else {
            $variant->setPrice($price);
        }
    }

    /**
     * Create a new menu variant.
     */
    private function createMenuVariant(
        Product $product,
        string $menuId,
        int $price,
        ?TaxCategory $defaultTaxCategory
    ): ProductVariant {
        /** @var ProductVariant $variant */
        $variant = $this->variantFactory->createForProduct($product);
        $variant->setCode(Uuid::uuid4()->toString());
        $variant->setPrice($price);

        if ($defaultTaxCategory !== null) {
            $variant->setTaxCategory($defaultTaxCategory);
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
            $this->importPartOptionValues($option, $part, $menu, $productsMap, $locale);
        }

        $this->em->flush();
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

        $option = $this->em->getRepository(ProductOption::class)->findOneBy([
            'code' => $optionCode,
        ]);

        if ($option === null) {
            $option = $this->createMenuPartOption($part, $partId, $restaurant, $locale);
        }

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
        $option->setCode(Uuid::uuid4()->toString());
        $option->setPosition(0);
        $option->setRestaurant($restaurant);
        $option->setCurrentLocale($locale);

        if ($part->name) {
            $option->setName($part->name);
        }

        $this->em->persist($option);
        $this->em->flush();

        return $option;
    }

    /**
     * Link a product option to a menu product if not already linked.
     */
    private function linkOptionToProduct(Product $menuProduct, ProductOption $option): void
    {
        $productId = $menuProduct->getId();
        $optionId = $option->getId();

        if ($productId === null || $optionId === null) {
            throw new \Exception("No product id or option id");
        }

        $existingProductOptions = $this->em->getRepository(ProductOptions::class)->findOneBy([
            'product' => $productId,
            'option' => $optionId,
        ]);

        if ($existingProductOptions === null) {
            $productOptions = new ProductOptions();
            $productOptions->setProduct($menuProduct);
            $productOptions->setOption($option);
            $productOptions->setEnabled(true);
            $this->em->persist($productOptions);
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
    ): void {
        $existingValues = $this->indexOptionValuesByCode($option);

        foreach ($part->dishIds as $position => $dishId) {
            $valueCode = sprintf('%s_%s', $part->id, $dishId);
            $value = $this->getOrCreatePartOptionValue($valueCode, $dishId, $existingValues, $productsMap, $menu, $locale);

            if (!$option->getValues()->contains($value)) {
                $option->addValue($value);
            }
        }

        $this->em->flush();
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
        // Metadata is already set on existing values, no need to update
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
        /** @var ProductOptionValue|null $value */
        $value = $this->em->getRepository(ProductOptionValue::class)->findOneBy([
            'code' => $valueCode,
        ]);

        if ($value === null) {
            $value = new ProductOptionValue();
            $value->setCode(Uuid::uuid4()->toString());
            $value->setZeltyCode($dishId);
            $value->setCurrentLocale($locale);

            [$dishName] = $this->extractDishInfo($dishId, $productsMap);
            $value->setValue($dishName ?? $dishId);
            $value->setEnabled(!$menu->disabled);

            $this->em->persist($value);
        }

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
}
