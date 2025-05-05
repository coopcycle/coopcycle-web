<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Api\Dto\CalculationItem;
use AppBundle\Api\Dto\CalculationOutput;
use AppBundle\Api\Resource\RetailPrice;
use AppBundle\Entity\Delivery;
use AppBundle\Pricing\PricingManager;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\SettingsManager;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CalculateRetailPrice implements TaxableInterface
{
    private ?TaxCategoryInterface $taxCategory = null;

	public function __construct(
        private readonly PricingManager $pricingManager,
        private readonly CurrencyContextInterface $currencyContext,
        private readonly SettingsManager $settingsManager,
        private readonly TokenStoreExtractor $storeExtractor,
        private readonly TaxCategoryRepositoryInterface $taxCategoryRepository,
        private readonly TaxRateResolverInterface $taxRateResolver,
        private readonly CalculatorInterface $calculator,
        private readonly NormalizerInterface $normalizer,
        private readonly string $state
    ) {}

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

        $pricingRuleSet = $store?->getPricingRuleSet();

        if (null === $pricingRuleSet) {
            $message = 'delivery.price.error.noPricingRuleSet';
            throw new BadRequestHttpException($message);
        }

        $priceCalculation = $this->pricingManager->getPriceCalculation($data, $pricingRuleSet);

        if (null === $priceCalculation) {
            $message = 'delivery.price.error.priceCalculation';
            throw new BadRequestHttpException($message);
        }

        $calculation = $priceCalculation->calculation;

        $calculationItems = [];
        foreach ($calculation->resultsPerEntity as $item) {
            $target = '';

            if (null !== $item->task) {
                $target = $item->task->getType();
            }

            if (null !== $item->delivery) {
                $target = 'ORDER';
            }

            $calculationItems[] = new CalculationItem(
                $target,
                $item->ruleResults
            );
        }
        $calculationOutput = new CalculationOutput(
            $calculation->ruleSet,
            $calculation->ruleSet->getStrategy(),
            $calculationItems
        );

        $order = $priceCalculation->order;

        if (null === $order) {
            $message = 'delivery.price.error.priceCalculation';

            // Serialize manually to preserve backwards compatibility
            return new JsonResponse(
                [
                    '@context' => "/api/contexts/Error",
                    '@type' => "hydra:Error",
                    'hydra:title' => "An error occurred",
                    'hydra:description' => $message,
                    'calculation' => $this->normalizer->normalize($calculationOutput, 'jsonld', [
                        'groups' => ['pricing_deliveries']
                    ]),
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $amount = $order->getItemsTotal();
        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $this->setTaxCategory(
            $this->taxCategoryRepository->findOneBy([
                'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT',
            ])
        );

        $taxRate   = $this->taxRateResolver->resolve($this, ['country' => strtolower($this->state)]);
        $taxAmount = (int) $this->calculator->calculate($amount, $taxRate);

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
