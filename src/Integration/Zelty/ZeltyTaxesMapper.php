<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Maps Zelty tax data to Sylius tax categories and rates.
 */
class ZeltyTaxesMapper implements ResetInterface
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
     * Same as setZeltyApiKey(), but the API log can attribute the calls to the shop.
     */
    public function setRestaurant(LocalBusiness $restaurant): void
    {
        $this->zeltyClient->setRestaurant($restaurant);
    }

    /**
     * The mapped tax categories are Doctrine entities, and this service outlives
     * the entity manager in a long-running worker: drop them when it is reset,
     * otherwise the next import would work with detached entities.
     */
    public function reset(): void
    {
        $this->taxCategoryMap = [];
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

        // A dish's tax_rules.tax_id comes through already prefixed, e.g. "ZTX4093",
        // while catalog/taxes hands back the bare numeric id — key the map to match
        // what resolveTaxCategory() in ZeltyProductMapper/ZeltyMenuMapper looks up.
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
     * Base tax categories ordered ascending by rate (lowest first), e.g.
     * [BASE_REDUCED, BASE_INTERMEDIARY, BASE_STANDARD] for France.
     *
     * Used by ZeltyMenuMapper to rank a menu's component dishes and pick the
     * highest one when Zelty gives no tax_rules for the menu itself.
     *
     * @return TaxCategory[]
     */
    public function getOrderedTaxCategories(): array
    {
        $categories = [];

        // getCategory() is typed ?TaxCategoryInterface at the interface level,
        // but rates always carry our own TaxCategory in practice; the instanceof
        // check narrows the return type for PHPStan and drops any null.
        foreach ($this->taxesHelper->getBaseRates() as $rate) {
            $category = $rate->getCategory();

            if ($category instanceof TaxCategory) {
                $categories[] = $category;
            }
        }

        return $categories;
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
