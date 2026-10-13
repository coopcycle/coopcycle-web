<?php

namespace Tests\AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Product\ProductVariantFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;

class ProductVariantFactoryTest extends TestCase
{
    use ProphecyTrait;

    private ProductVariantFactory $factory;

    public function setUp(): void
    {
        $product = new Product();
        $product->setCode('CPCCL-ODDLVR');

        $innerFactory = $this->prophesize(ProductVariantFactoryInterface::class);
        $innerFactory->createForProduct($product)->will(function () {
            // The name is translatable, so a locale is needed to set it
            $variant = new ProductVariant();
            $variant->setCurrentLocale('en');
            $variant->setFallbackLocale('en');

            return $variant;
        });

        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $productRepository->findOneBy(['code' => 'CPCCL-ODDLVR'])->willReturn($product);

        $taxCategoryRepository = $this->prophesize(TaxCategoryRepositoryInterface::class);
        $taxCategoryRepository->findOneBy(Argument::cetera())->willReturn(new TaxCategory());

        $settingsManager = $this->prophesize(SettingsManager::class);
        $settingsManager->get('subject_to_vat')->willReturn(true);

        $this->factory = new ProductVariantFactory(
            $innerFactory->reveal(),
            $productRepository->reveal(),
            $taxCategoryRepository->reveal(),
            $settingsManager->reveal()
        );
    }

    /**
     * Sylius maps ProductVariant::$position as a Gedmo sortable position
     * grouped by product, so giving a variant a position makes Gedmo relocate
     * every variant stored after it:
     *
     *   UPDATE sylius_product_variant SET position = position + 1
     *   WHERE position >= :position AND product_id = :onDemandDeliveryProduct
     *
     * On demand delivery variants are created one per order and never deleted,
     * so that group holds every delivery ever priced: a position here rewrites
     * the whole group on each order, and deadlocks concurrent ones.
     *
     * Leaving it unset makes Gedmo append, which skips the relocation.
     */
    public function testOnDemandDeliveryVariantIsLeftWithoutAPosition(): void
    {
        $delivery = new Delivery();
        $delivery->setDistance(1000);

        $variant = $this->factory->createWithPrice($delivery, new ArbitraryPrice('Delivery', 1000));

        $this->assertNull($variant->getPosition());
    }
}
