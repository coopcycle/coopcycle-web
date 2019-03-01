<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Prophecy\Argument;
use Sylius\Component\Taxation\Model\TaxCategory;
use Sylius\Component\Taxation\Model\TaxRate;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderTaxesProcessorTest extends KernelTestCase
{
    private $settingsManager;
    private $taxCategoryRepository;
    private $orderTaxesProcessor;
    private $taxCategory;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->taxCategoryRepository = $this->prophesize(TaxCategoryRepositoryInterface::class);

        $adjustmentFactory = static::$kernel->getContainer()->get('sylius.factory.adjustment');
        $calculator = static::$kernel->getContainer()->get('sylius.tax_calculator');
        $this->orderItemUnitFactory = static::$kernel->getContainer()->get('sylius.factory.order_item_unit');

        $taxRate20 = new TaxRate();
        $taxRate20->setName('TVA livraison');
        $taxRate20->setAmount(0.2);
        $taxRate20->setCalculator('default');
        $taxRate20->setIncludedInPrice(true);

        $taxCategory = new TaxCategory();
        $taxCategory->addRate($taxRate20);

        $this->settingsManager
            ->get('default_tax_category')
            ->willReturn('tva_livraison');

        $this->taxCategoryRepository
            ->findOneBy(['code' => 'tva_livraison'])
            ->willReturn($taxCategory);

        $taxRate10 = new TaxRate();
        $taxRate10->setName('TVA conso immédiate');
        $taxRate10->setAmount(0.1);
        $taxRate10->setCalculator('default');
        $taxRate10->setIncludedInPrice(true);

        $this->taxCategory = new TaxCategory();
        $this->taxCategory->addRate($taxRate10);

        $taxRateResolver = $this->prophesize(TaxRateResolverInterface::class);
        $taxRateResolver
            ->resolve(Argument::type(ProductVariantInterface::class))
            ->willReturn($taxRate10);

        $this->orderTaxesProcessor = new OrderTaxesProcessor(
            $adjustmentFactory,
            $taxRateResolver->reveal(),
            $calculator,
            $this->settingsManager->reveal(),
            $this->taxCategoryRepository->reveal()
        );
    }

    private function createOrderItem($unitPrice, $quantity = 1)
    {
        $productVariant = $this->prophesize(ProductVariantInterface::class);
        $productVariant
            ->getTaxCategory()
            ->willReturn($this->taxCategory);

        $orderItem = new OrderItem();
        $orderItem->setVariant($productVariant->reveal());
        $orderItem->setUnitPrice($unitPrice);

        for ($i = 0; $i < $quantity; ++$i) {
            $this->orderItemUnitFactory->createForItem($orderItem);
        }

        return $orderItem;
    }

    public function testEmptyOrder()
    {
        $order = new Order();

        $this->orderTaxesProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);

        $this->assertCount(0, $adjustments);
        $this->assertEquals(0, $order->getTaxTotal());
    }

    public function testOrderWithoutDelivery()
    {
        $order = new Order();
        $order->addItem($this->createOrderItem(1000));

        $this->assertEquals(1000, $order->getTotal());

        $this->orderTaxesProcessor->process($order);

        $adjustments = $order->getAdjustmentsRecursively(AdjustmentInterface::TAX_ADJUSTMENT);

        // Incl. tax = 1000
        // Tax total = (1000 - (1000 / (1 + 0.1))) = 91
        // Excl. tax = 909
        $this->assertCount(1, $adjustments);
        $this->assertEquals(91, $order->getTaxTotal());
    }

    public function testOrderWithDelivery()
    {
        $deliveryAdjustment = new Adjustment();
        $deliveryAdjustment->setType(AdjustmentInterface::DELIVERY_ADJUSTMENT);
        $deliveryAdjustment->setAmount(350);
        $deliveryAdjustment->setNeutral(false);

        $order = new Order();
        $order->addItem($this->createOrderItem(1000));
        $order->addAdjustment($deliveryAdjustment);

        $this->assertEquals(1350, $order->getTotal());

        $this->orderTaxesProcessor->process($order);

        // Incl. tax (items) = 1000
        // Tax total (items) = (1000 - (1000 / (1 + 0.1))) = 91
        // Incl. tax (delivery) = 350
        // Tax total (delivery) = (350 - (350 / (1 + 0.2))) = 58

        // Tax total (items + delivery) = 91 + 58 = 149
        $this->assertEquals(149, $order->getTaxTotal());

        $adjustments = $order->getAdjustmentsRecursively(AdjustmentInterface::TAX_ADJUSTMENT);
        $this->assertCount(2, $adjustments);

        $adjustments = $order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);
        $this->assertCount(1, $adjustments);

        // Incl. tax = 350
        // Tax total = (350 - (350 / (1 + 0.2))) = 58
        // Excl. tax = 292
        $this->assertEquals(58, $adjustments->first()->getAmount());
    }
}
