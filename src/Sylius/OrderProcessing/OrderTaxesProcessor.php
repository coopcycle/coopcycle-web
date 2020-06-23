<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class OrderTaxesProcessor implements OrderProcessorInterface, TaxableInterface
{
    private $adjustmentFactory;
    private $taxRateResolver;
    private $calculator;
    private $settingsManager;
    private $taxCategoryRepository;

    public function __construct(
        AdjustmentFactoryInterface $adjustmentFactory,
        TaxRateResolverInterface $taxRateResolver,
        CalculatorInterface $calculator,
        SettingsManager $settingsManager,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        TranslatorInterface $translator,
        string $state)
    {
        $this->adjustmentFactory = $adjustmentFactory;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->settingsManager = $settingsManager;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->translator = $translator;
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

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->clearTaxes($order);
        if ($order->isEmpty()) {
            return;
        }

        foreach ($order->getItems() as $orderItem) {

            $taxCategory = $orderItem->getVariant()->getTaxCategory();

            $adjustments = array_map(
                fn($rate) => $this->createAdjustmentWithRate($orderItem, $rate),
                $this->taxRateResolver->resolveAll($orderItem->getVariant())->toArray()
            );

            foreach ($adjustments as $adjustment) {
                $orderItem->addAdjustment($adjustment);
            }
        }

        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $this->setTaxCategory(
            $this->taxCategoryRepository->findOneBy([
                'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT',
            ])
        );

        foreach ($order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT) as $adjustment) {

            $taxRate = $this->taxRateResolver->resolve($this, ['country' => strtolower($this->state)]);

            $taxAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::TAX_ADJUSTMENT,
                $this->translator->trans($taxRate->getName(), [], 'taxation'),
                (int) $this->calculator->calculate($adjustment->getAmount(), $taxRate),
                $neutral = $taxRate->isIncludedInPrice()
            );
            $taxAdjustment->setOriginCode($taxRate->getCode());

            $order->addAdjustment($taxAdjustment);
        }
    }

    private function createAdjustmentWithRate($orderItem, $taxRate)
    {
        $taxAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::TAX_ADJUSTMENT,
            $this->translator->trans($taxRate->getName(), [], 'taxation'),
            (int) $this->calculator->calculate($orderItem->getTotal(), $taxRate),
            $neutral = $taxRate->isIncludedInPrice()
        );
        $taxAdjustment->setOriginCode($taxRate->getCode());

        return $taxAdjustment;
    }

    /**
     * @param BaseOrderInterface $order
     */
    private function clearTaxes(BaseOrderInterface $order): void
    {
        $order->removeAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);
        foreach ($order->getItems() as $item) {
            $item->removeAdjustmentsRecursively(AdjustmentInterface::TAX_ADJUSTMENT);
        }
    }
}
