<?php

namespace Tests\AppBundle\Sylius\Taxation\Resolver;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Sylius\Taxation\Resolver\TaxRateResolver;
use AppBundle\Sylius\Taxation\TaxesInitializer;
use AppBundle\Sylius\Taxation\TaxesProvider;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TaxRateResolverTest extends KernelTestCase implements TaxableInterface
{
    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->doctrine = self::$container->get('doctrine');

        $purger = new ORMPurger($this->doctrine->getManager());
        $purger->purge();

        $this->taxCategoryRepository = self::$container->get('sylius.repository.tax_category');
        $this->taxesProvider = self::$container->get(TaxesProvider::class);
        $this->taxRateRepository = self::$container->get('sylius.repository.tax_rate');

        $taxesInitializer = new TaxesInitializer(
            $this->doctrine->getConnection(),
            $this->taxesProvider,
            $this->taxCategoryRepository,
            $this->doctrine->getManagerForClass(TaxCategory::class)
        );

        $taxesInitializer->initialize();
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function getTaxCategory(): ?TaxCategoryInterface
    {
        return $this->taxCategory;
    }

    public function testResolvesRateWithCountry()
    {
        $expectations = [
            [ 'de', 'FOOD_TAKEAWAY', 0.19 ],
            [ 'fr', 'FOOD_TAKEAWAY', 0.10 ],
            [ 'de', 'BASE_REDUCED',  0.07 ],
            [ 'be', 'SERVICE',       0.21 ],
            [ 'es', 'SERVICE',       0.21 ],
        ];

        foreach ($expectations as $expectation) {

            [ $region, $code, $amount ] = $expectation;

            $resolver = new TaxRateResolver(
                $this->taxRateRepository,
                $region
            );

            $this->taxCategory =
                $this->taxCategoryRepository->findOneByCode($code);

            $rate = $resolver->resolve($this);

            $this->assertNotNull($rate);
            $this->assertEquals($amount, $rate->getAmount());
        }
    }

    public function testIgnoresCountryCriteria()
    {
        $resolver = new TaxRateResolver(
            $this->taxRateRepository,
            'de'
        );

        $this->taxCategory =
            $this->taxCategoryRepository->findOneByCode('FOOD_TAKEAWAY');

        $rate = $resolver->resolve($this, ['country' => 'fr']);

        $this->assertNotNull($rate);
        $this->assertEquals(0.19, $rate->getAmount());
    }

    public function testResolvesLegacyRate()
    {
        $taxCategory = new TaxCategory();
        $taxCategory->setName('TVA conso différée');
        $taxCategory->setCode('TVA_CONSO_DIFFEREE');

        $taxRate = new TaxRate();
        $taxRate->setName('TVA taux réduit');
        $taxRate->setCalculator('default');
        $taxRate->setCode('TVA_REDUIT');
        $taxRate->setAmount(0.1);

        $taxCategory->addRate($taxRate);

        $this->doctrine->getManagerForClass(TaxCategory::class)->persist($taxCategory);
        $this->doctrine->getManagerForClass(TaxCategory::class)->flush();

        $resolver = new TaxRateResolver(
            $this->taxRateRepository,
            'fr'
        );

        $this->taxCategory = $taxCategory;

        $rate = $resolver->resolve($this);

        $this->assertNotNull($rate);
        $this->assertSame($rate, $taxRate);
        $this->assertEquals(0.1, $rate->getAmount());
    }

    public function testResolvesRateWithCountryAndDates()
    {
        $expectations = [
            [ 'de', 'SERVICE', 0.19, '2020-06-30 12:00:00' ],
            [ 'de', 'SERVICE', 0.16, '2020-07-01 12:00:00' ],
            [ 'de', 'SERVICE', 0.19, '2021-01-01 12:00:00' ],
        ];

        foreach ($expectations as $expectation) {

            [ $region, $code, $amount, $now ] = $expectation;

            Carbon::setTestNow(Carbon::parse($now));

            $resolver = new TaxRateResolver(
                $this->taxRateRepository,
                $region
            );

            $this->taxCategory =
                $this->taxCategoryRepository->findOneByCode($code);

            $rate = $resolver->resolve($this);

            $this->assertNotNull($rate);
            $this->assertEquals($amount, $rate->getAmount());
        }
    }
}
