<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Psr\Log\LoggerInterface;

class ZeltyTaxesMapper
{
    private array $taxCategoryMap = [];

    public function __construct(
        private ZeltyClient $zeltyClient,
        private TaxesHelper $taxesHelper,
        private ?LoggerInterface $logger = null
    ) {
        //FIXME: Use the dependency injection to set the auth token
        // See how to set it based on context.
        $this->zeltyClient->setAuth('[REDACTED]');
    }

    public function importTaxes(): array
    {
        if (!empty($this->taxCategoryMap)) {
            return $this->taxCategoryMap;
        }

        $taxes = $this->zeltyClient->getTaxes();
        $coopcycleRates = $this->taxesHelper->getBaseRates();

        foreach ($taxes['taxes'] as $tax) {
            $targetRate = $tax['rate'] / 100;
            $coopcycleTax = $this->findMatchingTaxRate($coopcycleRates, $targetRate);

            if (null === $coopcycleTax) {
                $this->logger?->warning(sprintf(
                    'No matching tax rate for Zelty tax "%s" (ID: %d, rate: %d%%)',
                    $tax['name'] ?? 'Unknown',
                    $tax['id'],
                    $tax['rate']
                ));
                continue;
            }

            $this->taxCategoryMap[sprintf('ZTX%d', $tax['id'])] = $coopcycleTax->getCategory();
        }

        return $this->taxCategoryMap;
    }

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
