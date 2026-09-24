<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Integration\Zelty\Dto\ZeltyItem;
use AppBundle\Integration\Zelty\Dto\ZeltyPrice;
use AppBundle\Integration\Zelty\ZeltyImageMapper;
use AppBundle\Integration\Zelty\ZeltyProductMapper;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;

/**
 * Reproduces a production crash: re-importing a dish whose price changed in
 * Zelty since the last sync blew up with
 * "duplicate key value violates unique constraint … Key (code)=(ZD429815_variant)
 * already exists" — the old lookup matched the existing variant by *price*,
 * so a changed price made it invisible and importProductVariant() tried to
 * INSERT a second row under the same deterministic code.
 */
class ZeltyProductMapperTest extends TestCase
{
    public function testReimportWithAChangedPriceUpdatesTheExistingVariantInstead(): void
    {
        $product = new Product();
        $product->setCode('ZD429815');
        $product->setFallbackLocale('fr');
        $product->setCurrentLocale('fr');

        $existingVariant = new ProductVariant();
        $existingVariant->setCode('ZD429815_variant');
        $existingVariant->setFallbackLocale('fr');
        $existingVariant->setCurrentLocale('fr');
        $existingVariant->setPrice(500); // stale price from a previous import
        $product->addVariant($existingVariant);

        $productRepository = $this->createMock(ObjectRepository::class);
        $productRepository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => ($criteria['code'] ?? null) === 'ZD429815' ? $product : null
        );

        $optionsRepository = $this->createMock(ObjectRepository::class);
        $optionsRepository->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => $class === Product::class ? $productRepository : $optionsRepository
        );

        $productFactory = $this->createMock(ProductFactoryInterface::class);
        $variantFactory = $this->createMock(ProductVariantFactoryInterface::class);
        // Would be called if the (buggy) price-based lookup missed the
        // existing row and tried to create a second one under the same code.
        $variantFactory->expects($this->never())->method('createForProduct');

        $slugify = $this->createMock(SlugifyInterface::class);
        $imageMapper = $this->createMock(ZeltyImageMapper::class);

        $mapper = new ZeltyProductMapper($productFactory, $variantFactory, $em, $slugify, $imageMapper);

        $restaurant = new LocalBusiness();

        // Same Zelty dish id, price changed from 500 to 600 since the last sync.
        $dish = new ZeltyItem(
            id: 'ZD429815',
            type: ZeltyItem::TYPE_DISH,
            name: 'Frites maison',
            price: new ZeltyPrice(price: 600),
        );

        $productMap = $mapper->importDishes([$dish], $restaurant, [], 'fr', []);

        $resultProduct = $productMap['ZD429815'];
        $this->assertCount(1, $resultProduct->getVariants(), 'Must update the existing variant, not create a second one.');

        $variant = $resultProduct->getVariants()->first();
        $this->assertSame($existingVariant, $variant);
        $this->assertSame(600, $variant->getPrice());
        $this->assertSame('ZD429815_variant', $variant->getCode());
    }

    /**
     * A dish linking a shared modifier ("Taille pizza", offered by every
     * pizza) must not claim the option's *choices*: a value's product link
     * means "this choice IS that dish", and DisabledProductListener disables
     * the choice whenever that dish is disabled. Restaurant 82 had both pizza
     * sizes pointing at the pizza "Niçoise" — the first dish importing the
     * option won all its values, and disabling Niçoise took the sizes down
     * with it for every other pizza.
     */
    public function testLinkingAnOptionToADishDoesNotClaimTheOptionValues(): void
    {
        $option = new ProductOption();
        $option->setCode('ZO1234_82');
        $option->setFallbackLocale('fr');
        $option->setCurrentLocale('fr');

        foreach (['ZOV1_classique' => 'Classique (31cm)', 'ZOV2_petite' => 'Petite (26 cm)'] as $code => $label) {
            $value = new ProductOptionValue();
            $value->setCode($code);
            $value->setFallbackLocale('fr');
            $value->setCurrentLocale('fr');
            $value->setValue($label);
            $option->addValue($value);
        }

        $productRepository = $this->createMock(ObjectRepository::class);
        $productRepository->method('findOneBy')->willReturn(null);

        $optionsRepository = $this->createMock(ObjectRepository::class);
        $optionsRepository->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => $class === Product::class ? $productRepository : $optionsRepository
        );

        $product = new Product();
        $product->setFallbackLocale('fr');
        $product->setCurrentLocale('fr');

        $productFactory = $this->createMock(ProductFactoryInterface::class);
        $productFactory->method('createNew')->willReturn($product);

        $variantFactory = $this->createMock(ProductVariantFactoryInterface::class);
        $variantFactory->method('createForProduct')->willReturnCallback(function () use ($product) {
            $variant = new ProductVariant();
            $variant->setFallbackLocale('fr');
            $variant->setCurrentLocale('fr');
            $variant->setProduct($product);

            return $variant;
        });

        $slugify = $this->createMock(SlugifyInterface::class);
        $slugify->method('slugify')->willReturn('nicoise');
        $imageMapper = $this->createMock(ZeltyImageMapper::class);

        $mapper = new ZeltyProductMapper($productFactory, $variantFactory, $em, $slugify, $imageMapper);

        $dish = new ZeltyItem(
            id: 'ZD1000',
            type: ZeltyItem::TYPE_DISH,
            name: 'Niçoise',
            price: new ZeltyPrice(price: 1200),
            optionIds: ['ZO1234'],
        );

        $mapper->importDishes([$dish], new LocalBusiness(), ['ZO1234' => $option], 'fr', []);

        $this->assertTrue($product->hasOption($option), 'The option itself must still be linked to the dish.');

        foreach ($option->getValues() as $value) {
            $this->assertNull(
                $value->getProduct(),
                sprintf('"%s" is a size, not a dish — it must not be linked to any product.', $value->getCode())
            );
        }
    }
}
