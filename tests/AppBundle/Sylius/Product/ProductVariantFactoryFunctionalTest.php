<?php

declare(strict_types=1);

namespace Tests\AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Sylius\Product\ProductVariantFactory;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductVariantFactoryFunctionalTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ProductVariantFactory $productVariantFactory;

    public function setUp(): void
    {
        // SET UP SYMFONY
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->productVariantFactory = self::getContainer()->get(ProductVariantFactory::class);

        // LOAD AND PERSIST FIXTURES
        self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../../fixtures/ORM/setup_default.yml',
        ], $_SERVER, [], PurgeMode::createDeleteMode());
    }

    public function testVariantsAreAppendedWithoutShiftingExistingOnes(): void
    {
        $variants = [];
        for ($i = 0; $i < 3; $i++) {
            $variant = $this->productVariantFactory->createWithPrice(Delivery::create(), new ArbitraryPrice(null, 1000));

            $this->entityManager->persist($variant);
            $this->entityManager->flush();

            $variants[] = $variant;
        }

        foreach ($variants as $variant) {
            $this->entityManager->refresh($variant);
        }

        // Inserting at a fixed position would shift (and lock) all the existing variants
        $this->assertSame([0, 1, 2], array_map(fn($variant) => $variant->getPosition(), $variants));
    }
}
