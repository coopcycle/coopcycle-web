<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Webmozart\Assert\Assert;

final class OrderTaxesProcessor implements OrderProcessorInterface
{
    private $adjustmentFactory;
    private $taxRateResolver;
    private $calculator;

    public function __construct(
        AdjustmentFactoryInterface $adjustmentFactory,
        TaxRateResolverInterface $taxRateResolver,
        CalculatorInterface $calculator)
    {
        $this->adjustmentFactory = $adjustmentFactory;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
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

            $orderItem->addAdjustment($taxAdjustment);
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
