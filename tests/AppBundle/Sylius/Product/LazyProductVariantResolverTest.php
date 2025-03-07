<?php

namespace Tests\AppBundle\Sylius\Product;

use AppBundle\Sylius\Product\LazyProductVariantResolver;
use AppBundle\Business\Context as BusinessContext;
use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\BusinessRestaurantGroup;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Sylius\Taxation\TaxesInitializer;
use AppBundle\Sylius\Taxation\TaxesProvider;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Ramsey\Uuid\Uuid;

class LazyProductVariantResolverTest extends KernelTestCase
{
    use ProphecyTrait;

    private $lazyVariantResolver;
    private $variantFactory;
    private $defaultVariantResolver;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->doctrine = self::$container->get('doctrine');
        $this->entityManager = self::$container->get(EntityManagerInterface::class);

        $this->defaultVariantResolver = $this->prophesize(ProductVariantResolverInterface::class);
        $this->variantFactory = $this->prophesize(ProductVariantFactoryInterface::class);
        $this->businessContext = $this->prophesize(BusinessContext::class);

        $this->businessContext->isActive()->willReturn(false);

        $this->lazyVariantResolver = new LazyProductVariantResolver(
            $this->defaultVariantResolver->reveal(),
            $this->variantFactory->reveal(),
            $this->businessContext->reveal(),
            $this->entityManager
        );

        $this->taxCategoryRepository = self::$container->get('sylius.repository.tax_category');
        $this->taxesProvider = self::$container->get(TaxesProvider::class);
        $this->taxRateRepository = self::$container->get('sylius.repository.tax_rate');

        $this->taxesInitializer = new TaxesInitializer(
            $this->doctrine->getConnection(),
            $this->taxesProvider,
            $this->taxCategoryRepository,
            $this->doctrine->getManagerForClass(TaxCategory::class)
        );

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();

        $this->taxesInitializer->initialize();
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
        $product->setCurrentLocale('en');
        $product->setCode(Uuid::uuid4()->toString());

        $drink = new ProductOption();
        $drink->setCode(Uuid::uuid4()->toString());
        $drink->setCurrentLocale('en');

        $accompaniement = new ProductOption();
        $accompaniement->setCode(Uuid::uuid4()->toString());
        $accompaniement->setCurrentLocale('en');

        $product->addOption($drink);
        $product->addOption($accompaniement);

        $soda = new ProductOptionValue();
        $soda->setCode(Uuid::uuid4()->toString());
        $soda->setCurrentLocale('en');
        $soda->setOption($drink);

        $beer = new ProductOptionValue();
        $beer->setCode(Uuid::uuid4()->toString());
        $beer->setCurrentLocale('en');
        $beer->setOption($drink);

        $salad = new ProductOptionValue();
        $salad->setCode(Uuid::uuid4()->toString());
        $salad->setCurrentLocale('en');
        $salad->setOption($accompaniement);

        $fries = new ProductOptionValue();
        $fries->setCode(Uuid::uuid4()->toString());
        $fries->setCurrentLocale('en');
        $fries->setOption($accompaniement);

        $variantWithSodaAndSalad = new ProductVariant();
        $variantWithSodaAndSalad->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithSodaAndSalad->setCode(Uuid::uuid4()->toString());
        $variantWithSodaAndSalad->setPrice(500);
        $variantWithSodaAndSalad->addOptionValue($soda);
        $variantWithSodaAndSalad->addOptionValue($salad);

        $variantWithSodaAndFries = new ProductVariant();
        $variantWithSodaAndFries->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithSodaAndFries->setCode(Uuid::uuid4()->toString());
        $variantWithSodaAndFries->setPrice(500);
        $variantWithSodaAndFries->addOptionValue($soda);
        $variantWithSodaAndFries->addOptionValue($fries);

        $variantWithBeerAndSalad = new ProductVariant();
        $variantWithBeerAndSalad->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithBeerAndSalad->setCode(Uuid::uuid4()->toString());
        $variantWithBeerAndSalad->setPrice(500);
        $variantWithBeerAndSalad->addOptionValue($beer);
        $variantWithBeerAndSalad->addOptionValue($salad);

        $variantWithBeerAndFries = new ProductVariant();
        $variantWithBeerAndFries->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithBeerAndFries->setCode(Uuid::uuid4()->toString());
        $variantWithBeerAndFries->setPrice(500);
        $variantWithBeerAndFries->addOptionValue($beer);
        $variantWithBeerAndFries->addOptionValue($fries);

        $product->addVariant($variantWithSodaAndSalad);
        $product->addVariant($variantWithSodaAndFries);
        $product->addVariant($variantWithBeerAndSalad);
        $product->addVariant($variantWithBeerAndFries);

        $this->entityManager->persist($soda);
        $this->entityManager->persist($beer);
        $this->entityManager->persist($salad);
        $this->entityManager->persist($fries);

        $this->entityManager->persist($drink);
        $this->entityManager->persist($accompaniement);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

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
        $product->setCurrentLocale('en');
        $product->setCode(Uuid::uuid4()->toString());

        $drink = new ProductOption();
        $drink->setCode(Uuid::uuid4()->toString());
        $drink->setCurrentLocale('en');

        $sauces = new ProductOption();
        $sauces->setCode(Uuid::uuid4()->toString());
        $sauces->setCurrentLocale('en');
        $sauces->setAdditional(true);

        $soda = new ProductOptionValue();
        $soda->setCode(Uuid::uuid4()->toString());
        $soda->setCurrentLocale('en');
        $soda->setOption($drink);

        $ketchup = new ProductOptionValue();
        $ketchup->setCode(Uuid::uuid4()->toString());
        $ketchup->setCurrentLocale('en');
        $ketchup->setOption($sauces);

        $mustard = new ProductOptionValue();
        $mustard->setCode(Uuid::uuid4()->toString());
        $mustard->setCurrentLocale('en');
        $mustard->setOption($sauces);

        $variantWithDrinksOnly = new ProductVariant();
        $variantWithDrinksOnly->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithDrinksOnly->setCode(Uuid::uuid4()->toString());
        $variantWithDrinksOnly->setPrice(500);
        $variantWithDrinksOnly->addOptionValue($soda);

        $variantWithDrinksAndOneSauce = new ProductVariant();
        $variantWithDrinksAndOneSauce->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithDrinksAndOneSauce->setCode(Uuid::uuid4()->toString());
        $variantWithDrinksAndOneSauce->setPrice(500);
        $variantWithDrinksAndOneSauce->addOptionValue($soda);
        $variantWithDrinksAndOneSauce->addOptionValue($ketchup);

        $variantWithDrinksAndTwoSauces = new ProductVariant();
        $variantWithDrinksAndTwoSauces->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithDrinksAndTwoSauces->setCode(Uuid::uuid4()->toString());
        $variantWithDrinksAndTwoSauces->setPrice(500);
        $variantWithDrinksAndTwoSauces->addOptionValue($soda);
        $variantWithDrinksAndTwoSauces->addOptionValue($ketchup);
        $variantWithDrinksAndTwoSauces->addOptionValue($mustard);

        $product->addVariant($variantWithDrinksAndTwoSauces);
        $product->addVariant($variantWithDrinksAndOneSauce);
        $product->addVariant($variantWithDrinksOnly);

        $this->entityManager->persist($soda);
        $this->entityManager->persist($ketchup);
        $this->entityManager->persist($mustard);

        $this->entityManager->persist($drink);
        $this->entityManager->persist($sauces);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

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
        $product->setCurrentLocale('en');
        $product->setCode(Uuid::uuid4()->toString());

        $size = new ProductOption();
        $size->setCurrentLocale('en');
        $size->setCode(Uuid::uuid4()->toString());
        $size->setAdditional(true);

        $sauces = new ProductOption();
        $sauces->setCurrentLocale('en');
        $sauces->setCode(Uuid::uuid4()->toString());
        $sauces->setAdditional(true);

        $kingSize = new ProductOptionValue();
        $kingSize->setCurrentLocale('en');
        $kingSize->setCode(Uuid::uuid4()->toString());
        $kingSize->setOption($size);

        $ketchup = new ProductOptionValue();
        $ketchup->setCurrentLocale('en');
        $ketchup->setCode(Uuid::uuid4()->toString());
        $ketchup->setOption($sauces);

        $mustard = new ProductOptionValue();
        $mustard->setCurrentLocale('en');
        $mustard->setCode(Uuid::uuid4()->toString());
        $mustard->setOption($sauces);

        $variantWithNoOptions = new ProductVariant();
        $variantWithNoOptions->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithNoOptions->setCode(Uuid::uuid4()->toString());
        $variantWithNoOptions->setPrice(500);

        $variantWithKingSize = new ProductVariant();
        $variantWithKingSize->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithKingSize->setCode(Uuid::uuid4()->toString());
        $variantWithKingSize->setPrice(500);
        $variantWithKingSize->addOptionValue($kingSize);

        $variantWithKingSizeAndOneSauce = new ProductVariant();
        $variantWithKingSizeAndOneSauce->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variantWithKingSizeAndOneSauce->setCode(Uuid::uuid4()->toString());
        $variantWithKingSizeAndOneSauce->setPrice(500);
        $variantWithKingSizeAndOneSauce->addOptionValue($kingSize);
        $variantWithKingSizeAndOneSauce->addOptionValue($ketchup);

        $product->addVariant($variantWithKingSize);
        $product->addVariant($variantWithKingSizeAndOneSauce);
        $product->addVariant($variantWithNoOptions);

        $this->entityManager->persist($kingSize);
        $this->entityManager->persist($ketchup);
        $this->entityManager->persist($mustard);

        $this->entityManager->persist($size);
        $this->entityManager->persist($sauces);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

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
        $product->setCode(Uuid::uuid4()->toString());

        $ingredients = new ProductOption();
        $ingredients->setCurrentLocale('en');
        $ingredients->setCode(Uuid::uuid4()->toString());
        $ingredients->setAdditional(true);
        // setValuesRange

        $avocado = new ProductOptionValue();
        $avocado->setCurrentLocale('en');
        $avocado->setCode(Uuid::uuid4()->toString());
        $avocado->setOption($ingredients);

        $bacon = new ProductOptionValue();
        $bacon->setCurrentLocale('en');
        $bacon->setCode(Uuid::uuid4()->toString());
        $bacon->setOption($ingredients);

        $defaultVariant = new ProductVariant();
        $defaultVariant->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $defaultVariant->setCode(Uuid::uuid4()->toString());
        $defaultVariant->setPrice(900);

        $product->addVariant($defaultVariant);

        $this->entityManager->persist($avocado);
        $this->entityManager->persist($bacon);

        $this->entityManager->persist($ingredients);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

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
        $product->setCode(Uuid::uuid4()->toString());

        $ingredients = new ProductOption();
        $ingredients->setCurrentLocale('en');
        $ingredients->setCode(Uuid::uuid4()->toString());
        $ingredients->setAdditional(true);
        // setValuesRange

        $avocado = new ProductOptionValue();
        $avocado->setCurrentLocale('en');
        $avocado->setCode(Uuid::uuid4()->toString());
        $avocado->setOption($ingredients);

        $bacon = new ProductOptionValue();
        $bacon->setCurrentLocale('en');
        $bacon->setCode(Uuid::uuid4()->toString());
        $bacon->setOption($ingredients);

        $defaultVariant = new ProductVariant();
        $defaultVariant->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $defaultVariant->setCode(Uuid::uuid4()->toString());
        $defaultVariant->setPrice(900);

        $doubleAvocadoDoubleBacon = new ProductVariant();
        $doubleAvocadoDoubleBacon->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $doubleAvocadoDoubleBacon->setCode(Uuid::uuid4()->toString());
        $doubleAvocadoDoubleBacon->setPrice(900);
        $doubleAvocadoDoubleBacon->addOptionValueWithQuantity($avocado, 2);
        $doubleAvocadoDoubleBacon->addOptionValueWithQuantity($bacon, 2);

        $product->addVariant($defaultVariant);
        $product->addVariant($doubleAvocadoDoubleBacon);

        $this->entityManager->persist($avocado);
        $this->entityManager->persist($bacon);

        $this->entityManager->persist($ingredients);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

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
        $product->setCode(Uuid::uuid4()->toString());

        $ingredients = new ProductOption();
        $ingredients->setCurrentLocale('en');
        $ingredients->setCode(Uuid::uuid4()->toString());
        $ingredients->setAdditional(true);
        // setValuesRange

        $avocado = new ProductOptionValue();
        $avocado->setCurrentLocale('en');
        $avocado->setCode(Uuid::uuid4()->toString());
        $avocado->setOption($ingredients);

        $bacon = new ProductOptionValue();
        $bacon->setCurrentLocale('en');
        $bacon->setCode(Uuid::uuid4()->toString());
        $bacon->setOption($ingredients);

        $defaultVariant = new ProductVariant();
        $defaultVariant->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $defaultVariant->setCode(Uuid::uuid4()->toString());
        $defaultVariant->setPrice(900);

        $doubleAvocadoDoubleBacon = new ProductVariant();
        $doubleAvocadoDoubleBacon->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $doubleAvocadoDoubleBacon->setCode(Uuid::uuid4()->toString());
        $doubleAvocadoDoubleBacon->setPrice(900);
        $doubleAvocadoDoubleBacon->addOptionValueWithQuantity($avocado, 2);
        $doubleAvocadoDoubleBacon->addOptionValueWithQuantity($bacon, 2);

        $product->addVariant($defaultVariant);
        $product->addVariant($doubleAvocadoDoubleBacon);

        $this->entityManager->persist($avocado);
        $this->entityManager->persist($bacon);

        $this->entityManager->persist($ingredients);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

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
        $product->setCode(Uuid::uuid4()->toString());

        $option1 = new ProductOption();
        $option1->setCurrentLocale('en');
        $option1->setCode(Uuid::uuid4()->toString());
        $option1->setAdditional(false);

        $option2 = new ProductOption();
        $option2->setCurrentLocale('en');
        $option2->setCode(Uuid::uuid4()->toString());
        $option2->setAdditional(false);

        $option3 = new ProductOption();
        $option3->setCurrentLocale('en');
        $option3->setCode(Uuid::uuid4()->toString());
        $option3->setAdditional(false);

        $option4 = new ProductOption();
        $option4->setCurrentLocale('en');
        $option4->setCode(Uuid::uuid4()->toString());
        $option4->setAdditional(false);

        $optionValue1 = new ProductOptionValue();
        $optionValue1->setCurrentLocale('en');
        $optionValue1->setCode(Uuid::uuid4()->toString());
        $optionValue1->setOption($option1);

        $optionValue2 = new ProductOptionValue();
        $optionValue2->setCurrentLocale('en');
        $optionValue2->setCode(Uuid::uuid4()->toString());
        $optionValue2->setOption($option2);

        $optionValue3 = new ProductOptionValue();
        $optionValue3->setCurrentLocale('en');
        $optionValue3->setCode(Uuid::uuid4()->toString());
        $optionValue3->setOption($option3);

        $optionValue4 = new ProductOptionValue();
        $optionValue4->setCurrentLocale('en');
        $optionValue4->setCode(Uuid::uuid4()->toString());
        $optionValue4->setOption($option4);

        $variant1 = new ProductVariant();
        $variant1->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variant1->setCode(Uuid::uuid4()->toString());
        $variant1->setPrice(900);
        $variant1->addOptionValue($optionValue1);
        $variant1->addOptionValue($optionValue2);

        $defaultVariant = new ProductVariant();
        $defaultVariant->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $defaultVariant->setCode(Uuid::uuid4()->toString());
        $defaultVariant->setPrice(900);

        $product->addVariant($variant1);
        $product->addVariant($defaultVariant);

        $this->entityManager->persist($optionValue1);
        $this->entityManager->persist($optionValue2);
        $this->entityManager->persist($optionValue3);
        $this->entityManager->persist($optionValue4);

        $this->entityManager->persist($option1);
        $this->entityManager->persist($option2);
        $this->entityManager->persist($option3);
        $this->entityManager->persist($option4);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

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
        $product->setCode(Uuid::uuid4()->toString());

        $option1 = new ProductOption();
        $option1->setCurrentLocale('en');
        $option1->setCode(Uuid::uuid4()->toString());
        $option1->setAdditional(false);

        $option2 = new ProductOption();
        $option2->setCurrentLocale('en');
        $option2->setCode(Uuid::uuid4()->toString());
        $option2->setAdditional(false);

        $option3 = new ProductOption();
        $option3->setCurrentLocale('en');
        $option3->setCode(Uuid::uuid4()->toString());
        $option3->setAdditional(false);

        $option4 = new ProductOption();
        $option4->setCurrentLocale('en');
        $option4->setCode(Uuid::uuid4()->toString());
        $option4->setAdditional(false);

        $optionValue1 = new ProductOptionValue();
        $optionValue1->setCurrentLocale('en');
        $optionValue1->setCode(Uuid::uuid4()->toString());
        $optionValue1->setOption($option1);

        $optionValue2 = new ProductOptionValue();
        $optionValue2->setCurrentLocale('en');
        $optionValue2->setCode(Uuid::uuid4()->toString());
        $optionValue2->setOption($option2);

        $optionValue3 = new ProductOptionValue();
        $optionValue3->setCurrentLocale('en');
        $optionValue3->setCode(Uuid::uuid4()->toString());
        $optionValue3->setOption($option3);

        $optionValue4 = new ProductOptionValue();
        $optionValue4->setCurrentLocale('en');
        $optionValue4->setCode(Uuid::uuid4()->toString());
        $optionValue4->setOption($option4);

        $variant1 = new ProductVariant();
        $variant1->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $variant1->setCode(Uuid::uuid4()->toString());
        $variant1->setPrice(900);
        $variant1->addOptionValue($optionValue1);
        $variant1->addOptionValue($optionValue2);

        $defaultVariant = new ProductVariant();
        $defaultVariant->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $defaultVariant->setCode(Uuid::uuid4()->toString());
        $defaultVariant->setPrice(900);

        $product->addVariant($variant1);
        $product->addVariant($defaultVariant);

        $this->entityManager->persist($optionValue1);
        $this->entityManager->persist($optionValue2);
        $this->entityManager->persist($optionValue3);
        $this->entityManager->persist($optionValue4);

        $this->entityManager->persist($option1);
        $this->entityManager->persist($option2);
        $this->entityManager->persist($option3);
        $this->entityManager->persist($option4);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

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

    public function testGetVariantForBusiness()
    {
        $restaurantGroup = new BusinessRestaurantGroup();
        $restaurantGroup->setName('Acme');

        $businessAccount = new BusinessAccount();
        $businessAccount->setName('Acme');
        $businessAccount->setLegalName('Acme Ltd.');
        $businessAccount->setVatNumber('1234567890');
        $businessAccount->setBusinessRestaurantGroup($restaurantGroup);

        $this->businessContext->isActive()->willReturn(true);
        $this->businessContext->getBusinessAccount()->willReturn($businessAccount);

        $product = new Product();
        $product->setCurrentLocale('en');
        $product->setCode(Uuid::uuid4()->toString());

        $defaultVariant = new ProductVariant();
        $defaultVariant->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $defaultVariant->setCode(Uuid::uuid4()->toString());
        $defaultVariant->setPrice(900);

        $businessVariant = new ProductVariant();
        $businessVariant->setTaxCategory($this->taxCategoryRepository->findOneByCode('BASE_REDUCED'));
        $businessVariant->setCode(Uuid::uuid4()->toString());
        $businessVariant->setPrice(950);
        $businessVariant->setBusinessRestaurantGroup($restaurantGroup);

        $product->addVariant($defaultVariant);
        $product->addVariant($businessVariant);

        $this->entityManager->persist($restaurantGroup);
        $this->entityManager->persist($businessAccount);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $variant = $this->lazyVariantResolver
            ->getVariant($product);

        $this->assertSame($businessVariant, $variant);
    }
}
