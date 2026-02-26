<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOptions;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Integration\Zelty\Dto\ZeltyItem;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;

class ZeltyProductMapper
{
    public function __construct(
        private ProductFactoryInterface $productFactory,
        private ProductVariantFactoryInterface $variantFactory,
        private EntityManagerInterface $em,
        private SlugifyInterface $slugify,
    ) {}

    public function importDishes(array $dishes, LocalBusiness $restaurant, array $optionsMap, string $locale, array $taxesMap): array
    {
        $productMap = [];

        foreach ($dishes as $dish) {
            $product = $this->importDish($dish, $restaurant, $optionsMap, $locale, $taxesMap);
            $productMap[$product->getCode()] = $product;
        }

        return $productMap;
    }

    public function getProductByCode(string $code): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['code' => $code]);
    }

    private function importDish(ZeltyItem $dish, LocalBusiness $restaurant, array $optionsMap, string $locale, array $taxesMap): Product
    {
        $product = $this->em->getRepository(Product::class)->findOneBy([
            'code' => $dish->id,
        ]);

        if (null === $product) {
            /** @var Product $product */
            $product = $this->productFactory->createNew();
            $product->setCode($dish->id);
            $product->setRestaurant($restaurant);
            $product->setSlug($this->slugify->slugify(($dish->name ?? $dish->id) . '-' . $dish->id));

            $product->setCurrentLocale($locale);

            if ($dish->name) {
                $product->setName($dish->name);
            }

            if ($dish->description) {
                $product->setDescription($dish->description);
            }

            $this->em->persist($product);
        } elseif ($product->getRestaurant() !== $restaurant) {
            throw new \RuntimeException(sprintf(
                'Product with code "%s" already exists for a different restaurant',
                $dish->id
            ));
        } else {
            $product->setCurrentLocale($locale);
            if ($dish->name) {
                $product->setName($dish->name);
            }
            if ($dish->description) {
                $product->setDescription($dish->description);
            }
            $product->setEnabled(!$dish->disabled);
        }

        $this->importProductVariant($product, $dish, $taxesMap);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    private function importProductVariant(Product $product, ZeltyItem $dish, array $taxesMap): void
    {
        $price = $dish->price?->price ?? 0;

        $shouldCreateNewVariant = true;

        foreach ($product->getVariants() as $existingVariant) {
            if ($existingVariant->getPrice() === $price) {
                $shouldCreateNewVariant = false;
                break;
            }
        }

        if ($shouldCreateNewVariant) {
            /** @var ProductVariant $variant */
            $variant = $this->variantFactory->createForProduct($product);
            $variant->setCode(sprintf('%s_%s', $product->getCode(), $dish->id));
            $variant->setPrice($price);

            if ($dish->taxRule?->taxId && isset($taxesMap[$dish->taxRule->taxId])) {
                $variant->setTaxCategory($taxesMap[$dish->taxRule->taxId]);
            }

            $product->addVariant($variant);
            $this->em->persist($variant);
        }
    }

    /* private function linkOptions(Product $product, ZeltyItem $dish, array $optionsMap): void */
    /* { */
    /*     foreach ($dish->optionIds as $optionId) { */
    /*         if (isset($optionsMap[$optionId])) { */
    /*             $option = $optionsMap[$optionId]; */
    /**/
    /*             $existingProductOptions = $this->em->getRepository(ProductOptions::class)->findOneBy([ */
    /*                 'product' => $product, */
    /*                 'option' => $option, */
    /*             ]); */
    /**/
    /*             if (null === $existingProductOptions) { */
    /*                 $productOptions = new ProductOptions(); */
    /*                 $productOptions->setProduct($product); */
    /*                 $productOptions->setOption($option); */
    /*                 $productOptions->setEnabled(true); */
    /*                 $product->addOption($option); */
    /*                 $this->em->persist($productOptions); */
    /*             } */
    /*         } */
    /*     } */
    /* } */
}
