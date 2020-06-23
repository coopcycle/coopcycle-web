<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use AppBundle\Sylius\Product\ProductVariantInterface;
use AppBundle\Sylius\Taxation\Resolver\TaxRateResolver;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Model\TaxCategory;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderTaxesProcessorTest extends KernelTestCase
{
    use ProphecyTrait;

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
        $this->taxRateRepository = $this->prophesize(RepositoryInterface::class);

        $adjustmentFactory = static::$kernel->getContainer()->get('sylius.factory.adjustment');
        $calculator = static::$kernel->getContainer()->get('sylius.tax_calculator');
        $this->orderItemUnitFactory = static::$kernel->getContainer()->get('sylius.factory.order_item_unit');

        $taxRate20 = new TaxRate();
        $taxRate20->setName('TVA livraison');
        $taxRate20->setAmount(0.2);
        $taxRate20->setCalculator('default');
        $taxRate20->setIncludedInPrice(true);

        $taxRate10 = new TaxRate();
        $taxRate10->setName('TVA conso immédiate');
        $taxRate10->setAmount(0.1);
        $taxRate10->setCalculator('default');
        $taxRate10->setIncludedInPrice(true);

        $taxRate0 = new TaxRate();
        $taxRate0->setName('TVA Zéro');
        $taxRate0->setAmount(0.0);
        $taxRate0->setCalculator('default');
        $taxRate0->setIncludedInPrice(true);

        $serviceTax = $taxCategory = new TaxCategory();
        $taxCategory->addRate($taxRate20);

        $taxExempt = new TaxCategory();
        $taxExempt->addRate($taxRate0);

        $this->taxCategoryRepository
            ->findOneBy(['code' => 'SERVICE'])
            ->willReturn($taxCategory);

        $this->taxCategoryRepository
            ->findOneBy(['code' => 'SERVICE_TAX_EXEMPT'])
            ->willReturn($taxExempt);

        $foodTax = $this->taxCategory = new TaxCategory();
        $this->taxCategory->addRate($taxRate10);

        $this->taxRateRepository
            ->findOneBy(Argument::type('array'))
            ->will(function ($args) use ($foodTax, $serviceTax, $taxExempt, $taxRate10, $taxRate20, $taxRate0) {

                if (!isset($args[0]['country'])) {
                    return $taxRate10;
                }

                if ($args[0]['country'] === 'fr') {
                    if ($args[0]['category'] === $foodTax) {
                        return $taxRate10;
                    }
                    if ($args[0]['category'] === $serviceTax) {
                        return $taxRate20;
                    }
                    if ($args[0]['category'] === $taxExempt) {
                        return $taxRate0;
                    }
                }
            });

        $taxRateResolver = new TaxRateResolver(
            $this->taxRateRepository->reveal(),
            'fr'
        );

        $this->orderTaxesProcessor = new OrderTaxesProcessor(
            $adjustmentFactory,
            $taxRateResolver,
            $calculator,
            $this->settingsManager->reveal(),
            $this->taxCategoryRepository->reveal(),
            static::$kernel->getContainer()->get('translator'),
             'fr'
        );
    }

    private function createOrderItem($unitPrice, $quantity = 1, TaxCategory $taxCategory = null)
    {
        $productVariant = $this->prophesize(ProductVariantInterface::class);
        $productVariant
            ->getTaxCategory()
            ->willReturn($taxCategory ?? $this->taxCategory);

        $orderItem = new OrderItem();
        $orderItem->setVariant($productVariant->reveal());
        $orderItem->setUnitPrice($unitPrice);

        for ($i = 0; $i < $quantity; ++$i) {
            $this->orderItemUnitFactory->createForItem($orderItem);
        }

        return $orderItem;
    }

    private function subjectToVat(bool $subjectToVat)
    {
        $this->settingsManager
            ->get('subject_to_vat')
            ->willReturn($subjectToVat);
    }

    public function testEmptyOrder()
    {
        $this->subjectToVat(true);

        $order = new Order();

        $this->orderTaxesProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);

        $this->assertCount(0, $adjustments);
        $this->assertEquals(0, $order->getTaxTotal());
    }

    public function testOrderWithoutDelivery()
    {
        $this->subjectToVat(true);

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
        $this->subjectToVat(true);

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

    public function testOrderWithGstPst()
    {
        $gst = new TaxRate();
        $gst->setName('GST');
        $gst->setAmount(0.05);
        $gst->setCalculator('default');
        $gst->setIncludedInPrice(false);
        $gst->setCountry('ca-bc');

        $pst = new TaxRate();
        $pst->setName('PST');
        $pst->setAmount(0.07);
        $pst->setCalculator('default');
        $pst->setIncludedInPrice(false);
        $pst->setCountry('ca-bc');

        $taxCategory = new TaxCategory();
        $taxCategory->addRate($gst);
        $taxCategory->addRate($pst);

        $this->taxRateRepository
            ->findBy([
                'category' => $taxCategory,
                'country'  => 'fr',
            ])
            ->willReturn([
                $gst,
                $pst,
            ]);

        $order = new Order();
        $order->addItem($this->createOrderItem(1000, 1, $taxCategory));

        $this->assertEquals(1000, $order->getTotal());

        $this->orderTaxesProcessor->process($order);

        $this->assertEquals(1120, $order->getTotal());

        // Excl. tax (items) = 1000
        // Tax total (items) = (1000 * 0.05) + (1000 * 0.07) = 120
        $this->assertEquals(120, $order->getTaxTotal());

        $adjustments = $order->getAdjustmentsRecursively(AdjustmentInterface::TAX_ADJUSTMENT);
        $this->assertCount(2, $adjustments);

        $amounts = array_map(fn($adj) => $adj->getAmount(), $adjustments->toArray());

        $this->assertContains(50, $amounts);
        $this->assertContains(70, $amounts);
    }

    public function testOrderWithDeliveryTaxExempt()
    {
        $this->subjectToVat(false);

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

        // Tax total (items + delivery) = 91 + 0 = 91
        $this->assertEquals(91, $order->getTaxTotal());

        $adjustments = $order->getAdjustmentsRecursively(AdjustmentInterface::TAX_ADJUSTMENT);
        $this->assertCount(2, $adjustments);

        $adjustments = $order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);
        $this->assertCount(1, $adjustments);

        $this->assertEquals(0, $adjustments->first()->getAmount());
    }
}
