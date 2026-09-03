<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Integration\Zelty\ZeltyClient;
use AppBundle\Integration\Zelty\ZeltyTaxesMapper;
use AppBundle\Sylius\Taxation\TaxesHelper;
use PHPUnit\Framework\TestCase;

/**
 * Locks in the tax-id key format between ZeltyTaxesMapper (which builds the
 * map) and ZeltyProductMapper/ZeltyMenuMapper (which look it up).
 *
 * Behat's "the Zelty taxes API will return:" step is a sentinel that always
 * makes dishes fall back to the default tax category — see FeatureContext::
 * theZeltyTaxesApiWillReturn() — so it never exercises this matching logic.
 * This test uses the actual shapes Zelty's API returns: catalog/taxes hands
 * back a bare numeric id (e.g. 4093), while a dish's tax_rules.tax_id comes
 * back already prefixed ("ZTX4093").
 */
class ZeltyTaxesMapperTest extends TestCase
{
    public function testImportTaxesKeysTheMapByThePrefixedTaxId(): void
    {
        $reducedRate = $this->taxRate(5.5, 'BASE_REDUCED');
        $intermediaryRate = $this->taxRate(10.0, 'BASE_INTERMEDIARY');
        $standardRate = $this->taxRate(20.0, 'BASE_STANDARD');

        $zeltyClient = $this->createMock(ZeltyClient::class);
        $zeltyClient->method('getTaxes')->willReturn([
            'taxes' => [
                ['id' => 4093, 'name' => 'Tva importée 10%', 'rate' => 1000],
                ['id' => 4094, 'name' => 'Tva importée 5.5%', 'rate' => 550],
                ['id' => 4095, 'name' => 'Tva importée 20%', 'rate' => 2000],
            ],
        ]);

        $taxesHelper = $this->createMock(TaxesHelper::class);
        $taxesHelper->method('getBaseRates')->willReturn([
            $reducedRate, $intermediaryRate, $standardRate,
        ]);

        $mapper = new ZeltyTaxesMapper($zeltyClient, $taxesHelper);

        $map = $mapper->importTaxes();

        // This is the format a dish's tax_rules.tax_id is actually parsed into
        // (AppBundle\Integration\Zelty\Dto\ZeltyCatalogParser::parseTaxRule()).
        $this->assertSame($intermediaryRate->getCategory(), $map['ZTX4093'] ?? null);
        $this->assertSame($reducedRate->getCategory(), $map['ZTX4094'] ?? null);
        $this->assertSame($standardRate->getCategory(), $map['ZTX4095'] ?? null);

        // The bare id from catalog/taxes is not a valid key on its own.
        $this->assertArrayNotHasKey('4093', $map);
    }

    private function taxRate(float $percentage, string $categoryCode): TaxRate
    {
        $category = new TaxCategory();
        $category->setCode($categoryCode);

        $rate = new TaxRate();
        $rate->setAmount($percentage / 100);
        $rate->setCategory($category);

        return $rate;
    }
}
