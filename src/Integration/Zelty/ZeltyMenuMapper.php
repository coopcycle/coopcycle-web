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
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;

class ZeltyMenuMapper
{
    public function __construct(
        private ProductFactoryInterface $productFactory,
        private ProductVariantFactoryInterface $variantFactory,
        private EntityManagerInterface $em,
        private SlugifyInterface $slugify,
    ) {}

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

    private function importMenu(
        ZeltyItem $menu,
        array $menuPartsMap,
        array $productsMap,
        LocalBusiness $restaurant,
        string $locale,
        ?TaxCategory $defaultTaxCategory = null
    ): Product {
        $product = $this->em->getRepository(Product::class)->findOneBy([
            'code' => $menu->id,
        ]);

        if (null === $product) {
            /** @var Product $product */
            $product = $this->productFactory->createNew();
            $product->setCode($menu->id);
            $product->setRestaurant($restaurant);
            $product->setSlug($this->slugify->slugify(($menu->name ?? $menu->id) . '-' . $menu->id));

            $product->setCurrentLocale($locale);

            if ($menu->name) {
                $product->setName($menu->name);
            }

            if ($menu->description) {
                $product->setDescription($menu->description);
            }

            $product->setEnabled(!$menu->disabled);

            $this->em->persist($product);
        } elseif ($product->getRestaurant() !== $restaurant) {
            throw new \RuntimeException(sprintf(
                'Product with code "%s" already exists for a different restaurant',
                $menu->id
            ));
        } else {
            $product->setCurrentLocale($locale);
            if ($menu->name) {
                $product->setName($menu->name);
            }
            if ($menu->description) {
                $product->setDescription($menu->description);
            }
            $product->setEnabled(!$menu->disabled);
        }

        $this->importMenuVariant($product, $menu, $defaultTaxCategory);

        $this->importMenuPartsAsOptions($product, $menu, $menuPartsMap, $productsMap, $restaurant, $locale);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    private function importMenuVariant(Product $product, ZeltyItem $menu, ?TaxCategory $defaultTaxCategory = null): void
    {
        $price = $menu->price?->price ?? 0;

        $variant = $product->getVariants()->first() ?: null;

        if (null === $variant) {
            /** @var ProductVariant $variant */
            $variant = $this->variantFactory->createForProduct($product);
            $variant->setCode(sprintf('%s_variant', $menu->id));
            $variant->setPrice($price);

            if (null !== $defaultTaxCategory) {
                $variant->setTaxCategory($defaultTaxCategory);
            }

            $product->addVariant($variant);
            $this->em->persist($variant);
        } else {
            $variant->setPrice($price);
        }
    }

    private function importMenuPartsAsOptions(
        Product $menuProduct,
        ZeltyItem $menu,
        array $menuPartsMap,
        array $productsMap,
        LocalBusiness $restaurant,
        string $locale
    ): void {
        $existingOptions = [];
        foreach ($menuProduct->getOptions() as $option) {
            $existingOptions[$option->getCode()] = $option;
        }

        foreach ($menu->parts as $partId) {
            if (!isset($menuPartsMap[$partId])) {
                continue;
            }

            $part = $menuPartsMap[$partId];
            $optionCode = $partId;

            $option = $existingOptions[$optionCode] ?? null;

            if (null === $option) {
                /** @var ProductOption $option */
                $option = $this->em->getRepository(ProductOption::class)->findOneBy([
                    'code' => $optionCode,
                ]);

                if (null === $option) {
                    $option = new ProductOption();
                    $option->setCode($optionCode);
                    $option->setPosition(0);
                    $option->setRestaurant($restaurant);
                    $option->setCurrentLocale($locale);

                    if ($part->name) {
                        $option->setName($part->name);
                    }

                    $this->em->persist($option);
                }
            }

            $this->em->clear(ProductOptions::class);
            $existingProductOptions = $this->em->getRepository(ProductOptions::class)->findOneBy([
                'product' => $menuProduct,
                'option' => $option,
            ]);

            if (null === $existingProductOptions) {
                $productOptions = new ProductOptions();
                $productOptions->setProduct($menuProduct);
                $productOptions->setOption($option);
                $productOptions->setEnabled(true);
                $this->em->persist($productOptions);
            }

            $this->importPartOptionValues($option, $part, $menu, $productsMap, $locale);
        }

        $this->em->flush();
    }

    private function importPartOptionValues(
        ProductOption $option,
        ZeltyMenuPart $part,
        ZeltyItem $menu,
        array $productsMap,
        string $locale
    ): void {
        $existingValues = [];
        foreach ($option->getValues() as $value) {
            $existingValues[$value->getCode()] = $value;
        }

        foreach ($part->dishIds as $position => $dishId) {
            $valueCode = sprintf('%s_%s', $part->id, $dishId);

            $value = $existingValues[$valueCode] ?? null;

            if (null === $value) {
                /** @var ProductOptionValue $value */
                $value = $this->em->getRepository(ProductOptionValue::class)->findOneBy([
                    'code' => $valueCode,
                ]);

                if (null === $value) {
                    $value = new ProductOptionValue();
                    $value->setCode($valueCode);
                    $value->setCurrentLocale($locale);

                    $dishName = null;
                    $metadata = [];
                    if (isset($productsMap[$dishId])) {
                        $dishProduct = $productsMap[$dishId];
                        $dishName = $dishProduct->getName();
                        $metadata['dish_id'] = $dishId;
                        $metadata['dish_code'] = $dishProduct->getCode();
                    }

                    $value->setValue($dishName ?? $dishId);
                    $value->setEnabled(!$menu->disabled);
                    $value->setMetadata($metadata);

                    $this->em->persist($value);
                } else {
                    if (isset($productsMap[$dishId])) {
                        $dishProduct = $productsMap[$dishId];
                        $value->setMetadata([
                            'dish_id' => $dishId,
                            'dish_code' => $dishProduct->getCode(),
                        ]);
                    }
                }
            }

            if (!$option->getValues()->contains($value)) {
                $option->addValue($value);
            }
        }

        $this->em->flush();
    }
}
