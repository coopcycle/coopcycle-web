<?php

namespace Tests\AppBundle\Sylius\Taxation\Resolver;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Sylius\Taxation\Resolver\TaxRateResolver;
use AppBundle\Sylius\Taxation\TaxesInitializer;
use AppBundle\Sylius\Taxation\TaxesProvider;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
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

        $this->taxesInitializer = new TaxesInitializer(
            $this->doctrine->getConnection(),
            $this->taxesProvider,
            $this->taxCategoryRepository,
            $this->doctrine->getManagerForClass(TaxCategory::class)
        );
    }

    public function getTaxCategory(): ?TaxCategoryInterface
    {
        return $this->taxCategory;
    }

    private function createLegacyRate($categoryCode, $taxRateAmount)
    {
        $rate = new TaxRate();
        $rate->setName('Taux standard');
        $rate->setCode('TAUX_STANDARD');
        $rate->setCalculator('default');
        $rate->setAmount($taxRateAmount);

        $category = new TaxCategory();
        $category->setName('Boissons alcoolisées');
        $category->setCode($categoryCode);

        $category->addRate($rate);

        $this->doctrine->getManagerForClass(TaxCategory::class)->persist($category);
        $this->doctrine->getManagerForClass(TaxCategory::class)->flush();
    }

    public function testResolveWithLegacyRate()
    {
        $this->createLegacyRate('BOISSONS_ALCOOL', 0.2);

        $this->taxesInitializer->initialize();

        $resolver = new TaxRateResolver(
            $this->taxRateRepository,
            'FR'
        );

        $this->taxCategory = $this->taxCategoryRepository->findOneByCode('BOISSONS_ALCOOL');

        $rate = $resolver->resolve($this);

        $this->assertNotNull($rate);
        $this->assertEquals(0.2, $rate->getAmount());
    }

    public function testResolveAllWithLegacyRate()
    {
        $this->createLegacyRate('BOISSONS_ALCOOL', 0.2);

        $this->taxesInitializer->initialize();

        $resolver = new TaxRateResolver(
            $this->taxRateRepository,
            'FR'
        );

        $this->taxCategory = $this->taxCategoryRepository->findOneByCode('BOISSONS_ALCOOL');

        $rates = $resolver->resolveAll($this);

        $this->assertCount(1, $rates);
    }

    public function testResolveAllWithNewRate()
    {
        $this->taxesInitializer->initialize();

        $resolver = new TaxRateResolver(
            $this->taxRateRepository,
            'CA-BC'
        );

        $this->taxCategory = $this->taxCategoryRepository->findOneByCode('DRINK_ALCOHOL');

        $rates = $resolver->resolveAll($this);

        $this->assertCount(2, $rates);
    }
}
