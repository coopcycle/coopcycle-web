<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Api\Resource\RetailPrice;
use AppBundle\Entity\Delivery;
use AppBundle\Pricing\RuleResult;
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
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

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

        $priceCalculation = $this->deliveryManager->getPriceCalculation($data, $store->getPricingRuleSet());

        if (null === $priceCalculation) {
            $message = 'delivery.price.error.priceCalculation';
            throw new BadRequestHttpException($message);
        }

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

                return new CalculationItem(
                    $target,
                    $calculation->ruleSet->getStrategy(),
                    $item->ruleResults
                );
            },
            $calculation->resultsPerEntity
        );

        $order = $priceCalculation->getOrder();

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

class CalculationItem {
    /**
     * @param RuleResult[] $rules
     */
    public function __construct(
        #[Groups(['pricing_deliveries'])]
        public readonly string $target,
        #[Groups(['pricing_deliveries'])]
        public readonly string $strategy,
        #[Groups(['pricing_deliveries'])]
        public readonly array $rules,
    )
    {
    }
}
