<?php

namespace AppBundle\Service;

use AppBundle\Service\SettingsManager;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

final class PriceHelper implements TaxableInterface
{
    public function __construct(
        CurrencyContextInterface $currencyContext,
        SettingsManager $settingsManager,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        TaxRateResolverInterface $taxRateResolver,
        CalculatorInterface $calculator,
        string $state)
    {
        $this->currencyContext = $currencyContext;
        $this->settingsManager = $settingsManager;

        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->state = $state;
    }

    private function setTaxCategory(?TaxCategoryInterface $taxCategory): void
    {
        $this->taxCategory = $taxCategory;
    }

    public function getTaxCategory(): ?TaxCategoryInterface
    {
        return $this->taxCategory;
    }

    public function fromTaxIncludedAmount(int $taxIncludedAmount)
    {
        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $this->setTaxCategory(
            $this->taxCategoryRepository->findOneBy([
                'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT',
            ])
        );

        $taxRate   = $this->taxRateResolver->resolve($this, ['country' => strtolower($this->state)]);
        $taxAmount = (int) $this->calculator->calculate($taxIncludedAmount, $taxRate);

        return [
            'taxExcludedAmount' => ($taxIncludedAmount - $taxAmount),
            'taxIncludedAmount' => $taxIncludedAmount,
            'taxAmount' => $taxAmount,
            'currency' => $this->currencyContext->getCurrencyCode(),
        ];
    }
}
