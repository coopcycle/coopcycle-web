<?php

namespace Tests\AppBundle\Sylius\Product;

use AppBundle\Sylius\Product\LazyProductVariantResolver;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
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

        $option1 = new ProductOption();
        $option2 = new ProductOption();
        $option3 = new ProductOption();
        $option4 = new ProductOption();

        $optionValue1 = new ProductOptionValue();
        $optionValue1->setOption($option1);

        $optionValue2 = new ProductOptionValue();
        $optionValue2->setOption($option2);

        $optionValue3 = new ProductOptionValue();
        $optionValue3->setOption($option3);

        $optionValue4 = new ProductOptionValue();
        $optionValue4->setOption($option4);

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

    public function testNonExistingVariantWithMandatoryOptions()
    {
        $product = new Product();
        $product->setCurrentLocale('en');

        $option1 = new ProductOption();
        $option1->setAdditional(false);

        $option2 = new ProductOption();
        $option2->setAdditional(false);

        $option3 = new ProductOption();
        $option3->setAdditional(false);

        $option4 = new ProductOption();
        $option4->setAdditional(false);

        $optionValue1 = new ProductOptionValue();
        $optionValue1->setOption($option1);

        $optionValue2 = new ProductOptionValue();
        $optionValue2->setOption($option2);

        $optionValue3 = new ProductOptionValue();
        $optionValue3->setOption($option3);

        $optionValue4 = new ProductOptionValue();
        $optionValue4->setOption($option4);

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

    public function testNonExistingVariantWithMandatoryAndAdditionalOptions()
    {
        $product = new Product();
        $product->setCurrentLocale('en');

        $option1 = new ProductOption();
        $option1->setAdditional(false);

        $option2 = new ProductOption();
        $option2->setAdditional(false);

        $option3 = new ProductOption();
        $option3->setAdditional(false);

        $option4 = new ProductOption();
        $option4->setAdditional(true);

        $optionValue1 = new ProductOptionValue();
        $optionValue1->setOption($option1);

        $optionValue2 = new ProductOptionValue();
        $optionValue2->setOption($option2);

        $optionValue3 = new ProductOptionValue();
        $optionValue3->setOption($option3);

        $optionValue4 = new ProductOptionValue();
        $optionValue4->setOption($option4);

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
