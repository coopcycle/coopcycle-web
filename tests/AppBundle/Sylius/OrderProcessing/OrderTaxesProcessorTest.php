<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Entity\Sylius\Product as AppProduct;
use AppBundle\Entity\Sylius\ProductOption as AppProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue as AppProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant as AppProductVariant;
use AppBundle\Entity\Sylius\TaxCategory as AppTaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Integration\Zelty\ZeltyMenuVatVentilator;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use AppBundle\Sylius\Product\ProductVariantInterface;
use AppBundle\Sylius\Taxation\Resolver\TaxRateResolver;
use AppBundle\Sylius\Taxation\Resolver\TaxRateResolverInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
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

    /**
     * End-to-end proof that process() actually delegates to
     * ZeltyMenuVatVentilator when it's wired in, instead of only unit-testing
     * the ventilator in isolation. The math itself (proportional split by
     * ex-tax à la carte price) is covered exhaustively by
     * ZeltyMenuVatVentilatorTest; this just proves the two are connected
     * correctly and produce two separate TAX_ADJUSTMENT rows on one order
     * item instead of one row at the highest rate.
     */
    public function testZeltyMenuWithFoodAndBeerProducesTwoTaxAdjustments()
    {
        $this->subjectToVat(true);

        $foodCategory = new AppTaxCategory();
        $foodCategory->setCode('BASE_INTERMEDIARY');
        $alcoholCategory = new AppTaxCategory();
        $alcoholCategory->setCode('BASE_STANDARD');

        $foodRate = new TaxRate();
        $foodRate->setAmount(0.10);
        $foodRate->setIncludedInPrice(true);
        $foodRate->setCalculator('default');
        $foodRate->setCategory($foodCategory);

        $alcoholRate = new TaxRate();
        $alcoholRate->setAmount(0.20);
        $alcoholRate->setIncludedInPrice(true);
        $alcoholRate->setCalculator('default');
        $alcoholRate->setCategory($alcoholCategory);

        $taxRateResolver = $this->createMock(TaxRateResolverInterface::class);
        $resolve = function ($taxable) use ($foodRate, $alcoholRate) {
            return match ($taxable->getTaxCategory()?->getCode()) {
                'BASE_INTERMEDIARY' => $foodRate,
                'BASE_STANDARD' => $alcoholRate,
                default => null,
            };
        };
        $taxRateResolver->method('resolve')->willReturnCallback($resolve);
        $taxRateResolver->method('resolveAll')->willReturnCallback(
            fn ($taxable) => new ArrayCollection(array_filter([$resolve($taxable)]))
        );

        $burger = new AppProduct();
        $burger->setCode('ZD_BURGER');
        $burger->setZeltyId('ZD_BURGER');
        $burgerVariant = new AppProductVariant();
        $burgerVariant->setPrice(700);
        $burgerVariant->setTaxCategory($foodCategory);
        $burger->addVariant($burgerVariant);

        $beer = new AppProduct();
        $beer->setCode('ZD_BEER');
        $beer->setZeltyId('ZD_BEER');
        $beerVariant = new AppProductVariant();
        $beerVariant->setPrice(500);
        $beerVariant->setTaxCategory($alcoholCategory);
        $beer->addVariant($beerVariant);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => match ($criteria['code']) {
                'ZD_BURGER' => $burger,
                'ZD_BEER' => $beer,
                default => null,
            }
        );
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $calculator = static::$kernel->getContainer()->get('sylius.tax_calculator');

        $ventilator = new ZeltyMenuVatVentilator($em, $taxRateResolver, $calculator, 'fr');

        $orderTaxesProcessor = new OrderTaxesProcessor(
            static::$kernel->getContainer()->get('sylius.factory.adjustment'),
            $taxRateResolver,
            $calculator,
            $this->settingsManager->reveal(),
            $this->taxCategoryRepository->reveal(),
            static::$kernel->getContainer()->get('translator'),
            'fr',
            $ventilator,
        );

        $burgerOption = new AppProductOption();
        $burgerOption->setAdditional(false);
        $burgerValue = new AppProductOptionValue();
        $burgerValue->setOption($burgerOption);
        $burgerValue->setZeltyId('ZD_BURGER');

        $beerOption = new AppProductOption();
        $beerOption->setAdditional(false);
        $beerValue = new AppProductOptionValue();
        $beerValue->setOption($beerOption);
        $beerValue->setZeltyId('ZD_BEER');

        $menuProduct = new AppProduct();
        $menuProduct->setCode('ZM1');
        $menuProduct->setZeltyId('ZM1');

        $menuVariant = new AppProductVariant();
        $menuVariant->setProduct($menuProduct);
        $menuVariant->addOptionValue($burgerValue);
        $menuVariant->addOptionValue($beerValue);

        $orderItem = new OrderItem();
        $orderItem->setVariant($menuVariant);
        $orderItem->setUnitPrice(1200);
        $this->orderItemUnitFactory->createForItem($orderItem);

        $order = new Order();
        $order->addItem($orderItem);

        $orderTaxesProcessor->process($order);

        $adjustments = $orderItem->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);

        // Two rows — one per rate — not one row taxing the whole 1200 at 20%.
        $this->assertCount(2, $adjustments);

        $total = array_sum(array_map(fn ($adj) => $adj->getAmount(), $adjustments->toArray()));

        // If the old "highest rate wins" behavior were still in effect, the
        // whole 1200 would be taxed at 20%: tax = 1200 - round(1200/1.2) = 200.
        // Ventilating between 10% and 20% must collect strictly less than that.
        $this->assertLessThan(200, $total);
        $this->assertGreaterThan(0, $total);
    }
}
