<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Psr\Log\LoggerInterface;

/**
 * Maps Zelty tax data to Sylius tax categories and rates.
 */
class ZeltyTaxesMapper
{
    private array $taxCategoryMap = [];

    public function __construct(
        private ZeltyClient $zeltyClient,
        private TaxesHelper $taxesHelper,
        private ?LoggerInterface $logger = null
    ) { }

    /**
     * Initialize Zelty client with authentication.
     */
    public function setZeltyApiKey(string $key): void
    {
        $this->zeltyClient->setAuth($key);
    }

    /**
     * Import all taxes from Zelty.
     *
     * @return array Map of Zelty tax identifiers to Sylius TaxCategory entities
     */
    public function importTaxes(): array
    {
        if (!empty($this->taxCategoryMap)) {
            return $this->taxCategoryMap;
        }

        $taxes = $this->zeltyClient->getTaxes();
        $coopcycleRates = $this->taxesHelper->getBaseRates();

        foreach ($taxes['taxes'] as $tax) {
            $this->processTaxEntry($tax, $coopcycleRates);
        }

        return $this->taxCategoryMap;
    }

    /**
     * Process a single tax entry from Zelty.
     */
    private function processTaxEntry(array $tax, array $coopcycleRates): void
    {
        $targetRate = $tax['rate'] / 100;
        $coopcycleTax = $this->findMatchingTaxRate($coopcycleRates, $targetRate);

        if ($coopcycleTax === null) {
            $this->logWarning($tax);
            return;
        }

        $this->taxCategoryMap[sprintf('ZTX%d', $tax['id'])] = $coopcycleTax->getCategory();
    }

    /**
     * Log a warning when no matching tax rate is found.
     */
    private function logWarning(array $tax): void
    {
        $this->logger?->warning(sprintf(
            'No matching tax rate for Zelty tax "%s" (ID: %d, rate: %d%%)',
            $tax['name'] ?? 'Unknown',
            $tax['id'],
            $tax['rate']
        ));
    }

    /**
     * Get the default tax category.
     */
    public function getDefaultTaxCategory(): ?TaxCategory
    {
        $coopcycleRates = $this->taxesHelper->getBaseRates();

        if (empty($coopcycleRates)) {
            return null;
        }

        return $coopcycleRates[0]->getCategory();
    }

    /**
     * Find a matching tax rate based on percentage.
     */
    private function findMatchingTaxRate(array $coopcycleRates, float $targetRate): ?TaxRate
    {
        foreach ($coopcycleRates as $ccTax) {
            if ($ccTax->getAmountAsPercentage() === $targetRate) {
                return $ccTax;
            }
        }
        return null;
    }
}
