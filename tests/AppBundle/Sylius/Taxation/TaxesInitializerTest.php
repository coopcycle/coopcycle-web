<?php

namespace Tests\AppBundle\Sylius\Taxation\Resolver;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Sylius\Taxation\TaxesInitializer;
use AppBundle\Sylius\Taxation\TaxesProvider;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TaxesInitializerTest extends KernelTestCase
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

    public function testInitialize()
    {
        $this->taxesInitializer->initialize();

        $c = $this->taxCategoryRepository->findOneByCode('SERVICE');

        $this->assertNotNull($c);
    }

    public function testUpdatesRate()
    {
        $this->markTestSkipped();

        $taxCategory = new TaxCategory();
        $taxCategory->setName('tax_category.service');
        $taxCategory->setCode('SERVICE');

        $taxRate = new TaxRate();
        $taxRate->setName('tax_rate.standard');
        $taxRate->setCalculator('default');
        $taxRate->setCode('DE_SERVICE_STANDARD');
        $taxRate->setCountry('de');
        $taxRate->setAmount(0.19);

        $taxCategory->addRate($taxRate);

        $this->doctrine->getManagerForClass(TaxCategory::class)->persist($taxCategory);
        $this->doctrine->getManagerForClass(TaxCategory::class)->flush();

        $this->taxesInitializer->initialize();

        $c = $this->taxCategoryRepository->findOneByCode('SERVICE');

        foreach ($c->getRates() as $r) {
            if ($r->getCountry() === 'de') {
                $this->assertEquals(0.16, $r->getAmount());
            }
        }
    }
}
