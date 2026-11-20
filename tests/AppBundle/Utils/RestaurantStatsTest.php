<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Entity\Sylius\OrderVendor;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Fixtures\DatabasePurger;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Taxation\TaxesHelper;
use AppBundle\Utils\RestaurantStats;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sylius\Component\Order\Model\Adjustment;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Integration test (real DB, no mocks) for RestaurantStats' per-rate "items
 * total excl. tax" columns.
 *
 * This is the export the accountant reconciles their VAT declaration
 * against — see the "itemsTotalExclTaxTotals" saga: a naive
 * SUM(orderItem.total) per rate double-counted a ventilated item's total
 * across every rate it touched, a tax ÷ rate reconstruction fix amplified
 * cent-rounding, and the actual fix was to stop reconstructing altogether
 * and read the exact base each tax adjustment carries in its `details`
 * column (see OrderTaxesProcessor::createAdjustmentWithRate()). These tests
 * pin that down against the real schema so a future change can't
 * reintroduce any of the three.
 *
 * Builds orders directly against the schema (bypassing OrderTaxesProcessor,
 * which has its own coverage in OrderTaxesProcessorTest) so each scenario
 * controls exactly which tax adjustments exist and what their `details`
 * carry. The DB is purged before each test (see IncidentsFilteringFunctionalTest
 * for the same pattern), so every test starts from an empty schema — no
 * marker-scoped codes or manual FK-ordered cleanup needed.
 */
class RestaurantStatsTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        $dbPurger = self::getContainer()->get(DatabasePurger::class);
        $dbPurger->purge();
    }

    /**
     * The common case: one order item, one tax rate. Its excl-tax total is
     * exact — item total minus its one tax adjustment. This pins down the
     * "H3X0" regression: a single-rate order's column must equal the
     * order's real total_products_excl_tax, not something reconstructed via
     * tax ÷ rate that drifts a few cents off it.
     */
    public function testSingleRateItemExclTaxIsExactAndMatchesOrderTotal(): void
    {
        $intermediary = $this->persistBaseRate('BASE_INTERMEDIARY', 0.10);

        $stats = $this->buildStatsForOrder(1000, [
            ['rate' => $intermediary, 'amount' => 91, 'base' => 1000],
        ]);

        // Incl. tax = 1000, tax = 1000 - round(1000 / 1.10) = 91, excl. tax = 909.
        $this->assertSame(91, $stats->getRowValue($intermediary, 0, false));
        $this->assertSame(909, $stats->getRowValue('product.' . $intermediary, 0, false));
        $this->assertSame(909, $stats->getRowValue('total_products_excl_tax', 0, false));
    }

    /**
     * A ventilated item (Zelty menu bundling components at different
     * rates): one order item, two tax adjustments. Each rate's excl-tax
     * share must come from its own adjustment's stored `details.base`, not
     * from the item's full total — and the two shares must sum to exactly
     * the item's (and so the order's) real excl-tax total. This is the
     * "H3S0" regression: the naive SUM(orderItem.total)-per-rate query
     * attributed the item's whole total to *every* rate it touched, and the
     * later tax ÷ rate reconstruction, while not double-counting, could
     * still drift a few cents off the true sum through rounding.
     */
    public function testVentilatedItemExclTaxSharesSumToOrderTotal(): void
    {
        // Mirrors OrderTaxesProcessorTest's burger (700 @ 10%) + beer
        // (500 @ 20%) menu: tax = base - round(base / (1 + rate)).
        $intermediary = $this->persistBaseRate('BASE_INTERMEDIARY', 0.10);
        $standard = $this->persistBaseRate('BASE_STANDARD', 0.20);

        $stats = $this->buildStatsForOrder(1200, [
            ['rate' => $intermediary, 'amount' => 64, 'base' => 700],
            ['rate' => $standard, 'amount' => 83, 'base' => 500],
        ]);

        $this->assertSame(636, $stats->getRowValue('product.' . $intermediary, 0, false));
        $this->assertSame(417, $stats->getRowValue('product.' . $standard, 0, false));

        $totalExclTax = $stats->getRowValue('total_products_excl_tax', 0, false);
        $this->assertSame(1053, $totalExclTax);

        // The invariant the accountant's reconciliation actually depends on:
        // whatever the rate mix, the per-rate columns must add up to the
        // order's real excl-tax total — never more, never less.
        $this->assertSame(
            $totalExclTax,
            $stats->getRowValue('product.' . $intermediary, 0, false)
                + $stats->getRowValue('product.' . $standard, 0, false)
        );
    }

    /**
     * A tax adjustment with no `details.base` (pre-migration historical
     * data that somehow slipped through, or any future adjustment-creation
     * path that forgets to set it) contributes nothing to the excl-tax
     * column rather than silently reconstructing a value — this is a
     * deliberate simplification (see RestaurantStats::computeTaxes()), not
     * an oversight, and this test is what would catch it quietly regressing
     * back into a reconstruction. The tax total itself is unaffected, since
     * it only ever depends on the adjustment's `amount`, never on `details`.
     */
    public function testMissingDetailsBaseContributesNoExclTaxButTaxTotalIsUnaffected(): void
    {
        $reduced = $this->persistBaseRate('BASE_REDUCED', 0.055);

        $stats = $this->buildStatsForOrder(1000, [
            ['rate' => $reduced, 'amount' => 52, 'base' => null],
        ]);

        $this->assertSame(52, $stats->getRowValue($reduced, 0, false));
        $this->assertSame(0, $stats->getRowValue('product.' . $reduced, 0, false));
    }

    /**
     * Persists a tax category under one of the three fixed base-category
     * codes TaxesHelper::getBaseRates() recognizes, with a single French
     * rate under it. Returns the rate's code, which is what an adjustment's
     * origin_code and this test's assertions key on.
     */
    private function persistBaseRate(string $categoryCode, float $amount): string
    {
        $category = new TaxCategory();
        $category->setCode($categoryCode);
        $category->setName($categoryCode);
        $category->setCreatedAt(new \DateTime());
        $this->em->persist($category);

        $rateCode = "{$categoryCode}_RATE";

        $rate = new TaxRate();
        $rate->setCode($rateCode);
        $rate->setName($categoryCode);
        $rate->setAmount($amount);
        $rate->setIncludedInPrice(true);
        $rate->setCalculator('default');
        // Matches services.yaml's TaxesHelper $country: '%region_iso%'
        // (COOPCYCLE_REGION), not the country_iso/COOPCYCLE_COUNTRY one.
        $rate->setCountry('fr');
        $rate->setCategory($category);
        $rate->setCreatedAt(new \DateTime());
        $this->em->persist($rate);

        $this->em->flush();

        return $rateCode;
    }

    /**
     * @param array<int, array{rate: string, amount: int, base: ?int}> $adjustments
     */
    private function buildStatsForOrder(int $itemTotal, array $adjustments): RestaurantStats
    {
        $restaurant = new LocalBusiness();
        $restaurant->setName('Test restaurant');
        $this->em->persist($restaurant);

        // sylius_order_item.variant_id is NOT NULL; the variant's own tax
        // category is irrelevant here since each test sets the item's tax
        // adjustments by hand, so it just needs *a* persisted category —
        // every caller of this method has already created one via
        // persistBaseRate() by this point.
        $anyCategory = $this->em->getRepository(TaxCategory::class)->findOneBy([], ['id' => 'ASC']);

        $product = new Product();
        $product->setCode('test_product');
        $product->setCurrentLocale('fr');
        $product->setFallbackLocale('fr');
        $product->setName('Test product');
        $product->setSlug('test-product');
        $this->em->persist($product);

        $variant = new ProductVariant();
        $variant->setCode('test_product_variant');
        $variant->setCurrentLocale('fr');
        $variant->setFallbackLocale('fr');
        $variant->setPrice($itemTotal);
        $variant->setTaxCategory($anyCategory);
        $product->addVariant($variant);
        $this->em->persist($variant);

        $order = new Order();
        $order->setState(OrderInterface::STATE_FULFILLED);
        $order->setCreatedAt(new \DateTime('-1 hour'));
        $this->em->persist($order);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice($itemTotal);
        $this->em->persist($item);

        // getTotal() is derived from OrderItemUnit rows, not directly from
        // unitPrice — without this, the item's total (and so the order's,
        // added below) stays 0.
        self::getContainer()->get('sylius.factory.order_item_unit')->createForItem($item);

        // Added *after* the unit above so the order's own itemsTotal column
        // (what RestaurantStats' total_products_excl_tax reads) picks up the
        // item's real total — Order::addItem() only reads it once, at call
        // time.
        $order->addItem($item);

        $this->em->persist(new OrderVendor($order, $restaurant));
        $this->em->flush();

        foreach ($adjustments as $spec) {
            $adjustment = new Adjustment();
            $adjustment->setType(AdjustmentInterface::TAX_ADJUSTMENT);
            $adjustment->setAmount($spec['amount']);
            $adjustment->setNeutral(true);
            $adjustment->setOriginCode($spec['rate']);
            if (null !== $spec['base']) {
                $adjustment->setDetails(['base' => $spec['base']]);
            }
            $item->addAdjustment($adjustment);
            $this->em->persist($adjustment);
        }

        // RestaurantStats selects orders via a fulfillment domain event, not
        // the order's own timestamps — see RestaurantStats::getArrayResult().
        $this->em->getConnection()->executeStatement(
            "INSERT INTO sylius_order_event (aggregate_id, type, data, metadata, created_at)
             VALUES (?, 'order:fulfilled', '{}', '{}', now())",
            [$order->getId()]
        );

        $this->em->flush();

        return new RestaurantStats(
            $this->em,
            new \DateTime('-1 day'),
            new \DateTime('+1 day'),
            $restaurant,
            self::getContainer()->get(PaginatorInterface::class),
            'fr',
            self::getContainer()->get(TranslatorInterface::class),
            self::getContainer()->get(TaxesHelper::class),
        );
    }
}
