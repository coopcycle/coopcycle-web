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
use Prophecy\PhpUnit\ProphecyTrait;

class LazyProductVariantResolverTest extends TestCase
{
    use ProphecyTrait;

    private $lazyVariantResolver;
    private $variantFactory;
    private $defaultVariantResolver;

    public function setUp(): void
    {
        $this->defaultVariantResolver = $this->prophesize(ProductVariantResolverInterface::class);
        $this->variantFactory = $this->prophesize(ProductVariantFactoryInterface::class);

        $this->lazyVariantResolver = new LazyProductVariantResolver(
            $this->defaultVariantResolver->reveal(),
            $this->variantFactory->reveal()
        );
    }

    private function toSplObjectStorage(array $productOptionValues)
    {
        $storage = new \SplObjectStorage();
        foreach ($productOptionValues as $productOptionValue) {
            $storage->attach($productOptionValue);
        }

        return $storage;
    }

    public function testExistingVariantWithMandatoryOptions()
    {
        $product = new Product();

        $drink = new ProductOption();
        $accompaniement = new ProductOption();

        $soda = new ProductOptionValue();
        $soda->setOption($drink);

        $beer = new ProductOptionValue();
        $beer->setOption($drink);

        $salad = new ProductOptionValue();
        $salad->setOption($accompaniement);

        $fries = new ProductOptionValue();
        $fries->setOption($accompaniement);

        $variantWithSodaAndSalad = new ProductVariant();
        $variantWithSodaAndSalad->addOptionValue($soda);
        $variantWithSodaAndSalad->addOptionValue($salad);

        $variantWithSodaAndFries = new ProductVariant();
        $variantWithSodaAndFries->addOptionValue($soda);
        $variantWithSodaAndFries->addOptionValue($fries);

        $variantWithBeerAndSalad = new ProductVariant();
        $variantWithBeerAndSalad->addOptionValue($beer);
        $variantWithBeerAndSalad->addOptionValue($salad);

        $variantWithBeerAndFries = new ProductVariant();
        $variantWithBeerAndFries->addOptionValue($beer);
        $variantWithBeerAndFries->addOptionValue($fries);

        $product->addVariant($variantWithSodaAndSalad);
        $product->addVariant($variantWithSodaAndFries);
        $product->addVariant($variantWithBeerAndSalad);
        $product->addVariant($variantWithBeerAndFries);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$soda, $salad]));

        $this->assertSame($variantWithSodaAndSalad, $variant);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$soda, $fries]));

        $this->assertSame($variantWithSodaAndFries, $variant);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$beer, $salad]));

        $this->assertSame($variantWithBeerAndSalad, $variant);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$beer, $fries]));

        $this->assertSame($variantWithBeerAndFries, $variant);
    }

    public function testExistingVariantWithMandatoryAndAdditionalOptions()
    {
        $product = new Product();

        $drink = new ProductOption();
        $sauces = new ProductOption();
        $sauces->setAdditional(true);

        $soda = new ProductOptionValue();
        $soda->setOption($drink);

        $ketchup = new ProductOptionValue();
        $ketchup->setOption($sauces);

        $mustard = new ProductOptionValue();
        $mustard->setOption($sauces);

        $variantWithDrinksOnly = new ProductVariant();
        $variantWithDrinksOnly->addOptionValue($soda);

        $variantWithDrinksAndOneSauce = new ProductVariant();
        $variantWithDrinksAndOneSauce->addOptionValue($soda);
        $variantWithDrinksAndOneSauce->addOptionValue($ketchup);

        $variantWithDrinksAndTwoSauces = new ProductVariant();
        $variantWithDrinksAndTwoSauces->addOptionValue($soda);
        $variantWithDrinksAndTwoSauces->addOptionValue($ketchup);
        $variantWithDrinksAndTwoSauces->addOptionValue($mustard);

        $product->addVariant($variantWithDrinksAndTwoSauces);
        $product->addVariant($variantWithDrinksAndOneSauce);
        $product->addVariant($variantWithDrinksOnly);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$soda]));

        $this->assertSame($variantWithDrinksOnly, $variant);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$soda, $ketchup]));

        $this->assertSame($variantWithDrinksAndOneSauce, $variant);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$soda, $ketchup, $mustard]));

        $this->assertSame($variantWithDrinksAndTwoSauces, $variant);
    }

    public function testExistingVariantWithAdditionalOptionsOnly()
    {
        $product = new Product();

        $size = new ProductOption();
        $size->setAdditional(true);

        $sauces = new ProductOption();
        $sauces->setAdditional(true);

        $kingSize = new ProductOptionValue();
        $kingSize->setOption($size);

        $ketchup = new ProductOptionValue();
        $ketchup->setOption($sauces);

        $mustard = new ProductOptionValue();
        $mustard->setOption($sauces);

        $variantWithNoOptions = new ProductVariant();

        $variantWithKingSize = new ProductVariant();
        $variantWithKingSize->addOptionValue($kingSize);

        $variantWithKingSizeAndOneSauce = new ProductVariant();
        $variantWithKingSizeAndOneSauce->addOptionValue($kingSize);
        $variantWithKingSizeAndOneSauce->addOptionValue($ketchup);

        $product->addVariant($variantWithKingSize);
        $product->addVariant($variantWithKingSizeAndOneSauce);
        $product->addVariant($variantWithNoOptions);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$kingSize]));

        $this->assertSame($variantWithKingSize, $variant);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$kingSize, $ketchup]));

        $this->assertSame($variantWithKingSizeAndOneSauce, $variant);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([]));

        $this->assertSame($variantWithNoOptions, $variant);
    }

    public function testNonExistingVariantWithAdditionalOptionsWithQuantity()
    {
        $product = new Product();
        $product->setCurrentLocale('en');

        $ingredients = new ProductOption();
        $ingredients->setAdditional(true);
        // setValuesRange

        $avocado = new ProductOptionValue();
        $avocado->setOption($ingredients);

        $bacon = new ProductOptionValue();
        $bacon->setOption($ingredients);

        $defaultVariant = new ProductVariant();
        $defaultVariant->setPrice(900);

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

        $optionValues = new \SplObjectStorage();
        $optionValues->attach($avocado, 2);
        $optionValues->attach($bacon, 3);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $optionValues);

        $this->assertEquals(900, $variant->getPrice());
        $this->assertTrue($variant->hasOptionValue($avocado));
        $this->assertTrue($variant->hasOptionValue($bacon));
    }

    public function testExistingVariantWithAdditionalOptionsWithQuantity()
    {
        $product = new Product();
        $product->setCurrentLocale('en');

        $ingredients = new ProductOption();
        $ingredients->setAdditional(true);
        // setValuesRange

        $avocado = new ProductOptionValue();
        $avocado->setOption($ingredients);

        $bacon = new ProductOptionValue();
        $bacon->setOption($ingredients);

        $defaultVariant = new ProductVariant();
        $defaultVariant->setPrice(900);

        $doubleAvocadoDoubleBacon = new ProductVariant();
        $doubleAvocadoDoubleBacon->addOptionValueWithQuantity($avocado, 2);
        $doubleAvocadoDoubleBacon->addOptionValueWithQuantity($bacon, 2);

        $product->addVariant($defaultVariant);
        $product->addVariant($doubleAvocadoDoubleBacon);

        $optionValues = new \SplObjectStorage();
        $optionValues->attach($avocado, 2);
        $optionValues->attach($bacon, 2);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $optionValues);

        $this->assertSame($doubleAvocadoDoubleBacon, $variant);
    }

    public function testExistingVariantMatchesWithQuantity()
    {
        $product = new Product();
        $product->setCurrentLocale('en');

        $ingredients = new ProductOption();
        $ingredients->setAdditional(true);
        // setValuesRange

        $avocado = new ProductOptionValue();
        $avocado->setOption($ingredients);

        $bacon = new ProductOptionValue();
        $bacon->setOption($ingredients);

        $defaultVariant = new ProductVariant();
        $defaultVariant->setPrice(900);

        $doubleAvocadoDoubleBacon = new ProductVariant();
        $doubleAvocadoDoubleBacon->addOptionValueWithQuantity($avocado, 2);
        $doubleAvocadoDoubleBacon->addOptionValueWithQuantity($bacon, 2);

        $product->addVariant($defaultVariant);
        $product->addVariant($doubleAvocadoDoubleBacon);

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

        $optionValues = new \SplObjectStorage();
        $optionValues->attach($avocado, 3); // TRIPLE avocado
        $optionValues->attach($bacon, 2);

        $variant = $this->lazyVariantResolver
            ->getVariantForOptionValues($product, $optionValues);

        $this->assertNotSame($doubleAvocadoDoubleBacon, $variant);

        $this->assertEquals(900, $variant->getPrice());
        $this->assertTrue($variant->hasOptionValue($avocado));
        $this->assertTrue($variant->hasOptionValue($bacon));
        $this->assertTrue($variant->hasOptionValueWithQuantity($avocado, 3));
        $this->assertTrue($variant->hasOptionValueWithQuantity($bacon, 2));
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
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$optionValue3, $optionValue4]));

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
            ->getVariantForOptionValues($product, $this->toSplObjectStorage([$optionValue3, $optionValue4]));

        $this->assertEquals(900, $actualVariant->getPrice());
        $this->assertTrue($actualVariant->hasOptionValue($optionValue3));
        $this->assertTrue($actualVariant->hasOptionValue($optionValue4));
    }
}
