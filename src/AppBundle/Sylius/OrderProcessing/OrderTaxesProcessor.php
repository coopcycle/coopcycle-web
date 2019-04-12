<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Webmozart\Assert\Assert;

final class OrderTaxesProcessor implements OrderProcessorInterface
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
        TaxCategoryRepositoryInterface $taxCategoryRepository)
    {
        $this->adjustmentFactory = $adjustmentFactory;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->settingsManager = $settingsManager;
        $this->taxCategoryRepository = $taxCategoryRepository;
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

            $taxRate = $this->taxRateResolver->resolve($orderItem->getVariant());

            $taxAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::TAX_ADJUSTMENT,
                $taxRate->getName(),
                (int) $this->calculator->calculate($orderItem->getTotal(), $taxRate),
                $neutral = true
            );
            $taxAdjustment->setOriginCode($taxRate->getCode());

            $orderItem->addAdjustment($taxAdjustment);
        }

        $taxCategory = $this->taxCategoryRepository->findOneBy([
            'code' => $this->settingsManager->get('default_tax_category')
        ]);

        foreach ($order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT) as $adjustment) {
            $taxRate = $taxCategory->getRates()->get(0);

            $taxAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::TAX_ADJUSTMENT,
                $taxRate->getName(),
                (int) $this->calculator->calculate($adjustment->getAmount(), $taxRate),
                $neutral = true
            );
            $taxAdjustment->setOriginCode($taxRate->getCode());

            $order->addAdjustment($taxAdjustment);
        }
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
