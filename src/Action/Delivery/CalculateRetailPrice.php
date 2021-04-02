<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Api\Resource\RetailPrice;
use AppBundle\Entity\Delivery;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Security\TokenStoreExtractor;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CalculateRetailPrice implements TaxableInterface
{
	public function __construct(
        DeliveryManager $deliveryManager,
        CurrencyContextInterface $currencyContext,
        SettingsManager $settingsManager,
        TokenStoreExtractor $storeExtractor,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        TaxRateResolverInterface $taxRateResolver,
        CalculatorInterface $calculator,
        string $state)
    {
        $this->deliveryManager = $deliveryManager;
        $this->currencyContext = $currencyContext;
        $this->settingsManager = $settingsManager;
        $this->storeExtractor = $storeExtractor;

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

    public function __invoke(Delivery $data, Request $request)
    {
        $store = $data->getStore();
        if (null === $store) {
            $store = $this->storeExtractor->extractStore();
        }

        $amount = $this->deliveryManager->getPrice($data, $store->getPricingRuleSet());

        if (null === $amount) {
            throw new BadRequestHttpException('Price could not be calculated');
        }

        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $this->setTaxCategory(
            $this->taxCategoryRepository->findOneBy([
                'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT',
            ])
        );

        $taxRate   = $this->taxRateResolver->resolve($this, ['country' => strtolower($this->state)]);
        $taxAmount = (int) $this->calculator->calculate($amount, $taxRate);

        $retailPrice = new RetailPrice(
            $amount,
            $this->currencyContext->getCurrencyCode(),
            $taxAmount,
            'included' === $request->query->get('tax', 'included')
        );

        return $retailPrice;
    }
}
