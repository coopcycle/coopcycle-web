<?php

namespace AppBundle\Service;

use AppBundle\Service\SettingsManager;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

final class PriceHelper implements TaxableInterface
{
    public $taxCategory;
    public function __construct(
        private CurrencyContextInterface $currencyContext,
        private SettingsManager $settingsManager,
        private TaxCategoryRepositoryInterface $taxCategoryRepository,
        private TaxRateResolverInterface $taxRateResolver,
        private CalculatorInterface $calculator,
        private string $state)
    {}

    private function setTaxCategory(?TaxCategoryInterface $taxCategory): void
    {
        $this->taxCategory = $taxCategory;
    }

    public function getTaxCategory(): ?TaxCategoryInterface
    {
        return $this->taxCategory;
    }

    /**
     * The rate a delivery is taxed at, as a fraction: 0.2 for 20%, 0.0 where the
     * cooperative is not subject to VAT. Pricing rules store their prices with tax
     * included, so this is what turns one into the other.
     */
    public function getTaxRateAmount(): float
    {
        $taxRate = $this->resolveTaxRate();

        return null !== $taxRate ? $taxRate->getAmount() : 0.0;
    }

    private function resolveTaxRate(): ?TaxRateInterface
    {
        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $this->setTaxCategory(
            $this->taxCategoryRepository->findOneBy([
                'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT',
            ])
        );

        return $this->taxRateResolver->resolve($this, ['country' => strtolower($this->state)]);
    }

    public function fromTaxIncludedAmount(int $taxIncludedAmount)
    {
        $taxRate   = $this->resolveTaxRate();
        $taxAmount = (int) $this->calculator->calculate($taxIncludedAmount, $taxRate);

        return [
            'taxExcludedAmount' => ($taxIncludedAmount - $taxAmount),
            'taxIncludedAmount' => $taxIncludedAmount,
            'taxAmount' => $taxAmount,
            'currency' => $this->currencyContext->getCurrencyCode(),
        ];
    }
}
