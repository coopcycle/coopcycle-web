<?php

namespace Tests\AppBundle\Sylius\Product;

use AppBundle\Sylius\Product\LazyProductVariantResolver;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use PHPUnit\Framework\TestCase;

class LazyProductVariantResolverTest extends TestCase
{
    private $lazyVariantResolver;
    private $variantFactory;
    private $defaultVariantResolver;

    public function setUp()
    {
        $this->defaultVariantResolver = $this->prophesize(ProductVariantResolverInterface::class);
        $this->variantFactory = $this->prophesize(ProductVariantFactoryInterface::class);

        $this->lazyVariantResolver = new LazyProductVariantResolver(
            $this->defaultVariantResolver->reveal(),
            $this->variantFactory->reveal()
        );
    }

    public function testExistingVariant()
    {
        $product = new Product();

        $optionValue1 = new ProductOptionValue();
        $optionValue2 = new ProductOptionValue();
        $optionValue3 = new ProductOptionValue();
        $optionValue4 = new ProductOptionValue();

        $variant1 = new ProductVariant();
        $variant1->addOptionValue($optionValue1);
        $variant1->addOptionValue($optionValue2);

        $variant2 = new ProductVariant();
        $variant2->addOptionValue($optionValue3);
        $variant2->addOptionValue($optionValue4);

        $product->addVariant($variant1);
        $product->addVariant($variant2);

        $actualVariant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, [$optionValue1, $optionValue2]);

        $this->assertSame($variant1, $actualVariant);
    }

    public function testNonExistingVariant()
    {
        $product = new Product();
        $product->setCurrentLocale('en');

        $optionValue1 = new ProductOptionValue();
        $optionValue2 = new ProductOptionValue();
        $optionValue3 = new ProductOptionValue();
        $optionValue4 = new ProductOptionValue();

        $variant1 = new ProductVariant();
        $variant1->addOptionValue($optionValue1);
        $variant1->addOptionValue($optionValue2);

        $defaultVariant = new ProductVariant();
        $defaultVariant->setPrice(900);

        $product->addVariant($variant1);
        $product->addVariant($defaultVariant);

        $this->variantFactory
            ->createForProduct($product)
            ->will(function ($args) use ($product) {
                $variant = new ProductVariant();
                $variant->setCurrentLocale('en');
                $variant->setProduct($product);

                return $variant;
            });

        $this->defaultVariantResolver
            ->getVariant($product)
            ->willReturn($defaultVariant);

        $actualVariant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, [$optionValue3, $optionValue4]);

        $this->assertEquals(900, $actualVariant->getPrice());
        $this->assertTrue($actualVariant->hasOptionValue($optionValue3));
        $this->assertTrue($actualVariant->hasOptionValue($optionValue4));
    }
}
