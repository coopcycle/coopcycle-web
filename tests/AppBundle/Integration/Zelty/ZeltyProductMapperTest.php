<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
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
}
