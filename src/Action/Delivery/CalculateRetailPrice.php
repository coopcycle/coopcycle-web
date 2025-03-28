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
use Symfony\Contracts\Translation\TranslatorInterface;

class CalculateRetailPrice implements TaxableInterface
{
    private ?TaxCategoryInterface $taxCategory = null;

	public function __construct(
        private readonly DeliveryManager $deliveryManager,
        private readonly CurrencyContextInterface $currencyContext,
        private readonly SettingsManager $settingsManager,
        private readonly TokenStoreExtractor $storeExtractor,
        private readonly TaxCategoryRepositoryInterface $taxCategoryRepository,
        private readonly TaxRateResolverInterface $taxRateResolver,
        private readonly CalculatorInterface $calculator,
        private readonly TranslatorInterface $translator,
        private readonly string $state)
    {
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

        $priceCalculation = $this->deliveryManager->getPriceCalculation($data, $store->getPricingRuleSet());

        if (null === $priceCalculation) {
            $message = $this->translator->trans('delivery.price.error.priceCalculation', [], 'validators');
            throw new BadRequestHttpException($message);
        }

        $order = $priceCalculation->getOrder();

        $amount = $order->getItemsTotal();
        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $this->setTaxCategory(
            $this->taxCategoryRepository->findOneBy([
                'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT',
            ])
        );

        $taxRate   = $this->taxRateResolver->resolve($this, ['country' => strtolower($this->state)]);
        $taxAmount = (int) $this->calculator->calculate($amount, $taxRate);

        $calculation = $priceCalculation->getCalculation();

        $calculationOutput = array_map(
            function ($item) use ($calculation) {
                $target = '';

                if (null !== $item->task) {
                    $target = $item->task->getType();
                }

                if (null !== $item->delivery) {
                    $target = 'ORDER';
                }

                return [
                    'target' => $target,
                    'strategy' => $calculation->ruleSet->getStrategy(),
                    'rules' => $item->ruleResults,
                ];
            },
            $calculation->resultsPerEntity
        );

        return new RetailPrice(
            $order->getItems(),
            $calculationOutput,
            $amount,
            $this->currencyContext->getCurrencyCode(),
            $taxAmount,
            'included' === $request->query->get('tax', 'included')
        );
    }
}
