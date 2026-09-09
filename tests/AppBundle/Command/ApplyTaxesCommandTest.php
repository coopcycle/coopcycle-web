<?php

namespace Tests\AppBundle\Command;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Reproduces the production report: re-import the Zelty catalog (variant's
 * tax category changes from reduced to intermediary), re-run
 * `coopcycle:orders:process-taxes`, and check whether the order item's
 * sylius_adjustment row actually follows.
 *
 * Runs against the real database/ORM (no mocks) because the earlier,
 * mock-based OrderTaxesProcessorTest already proves the processor's own
 * logic reads the variant's *current* tax category correctly — if that
 * were the whole story this bug wouldn't reproduce there. This test isolates
 * whatever happens once Doctrine persistence enters the picture.
 */
class ApplyTaxesCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private array $cleanupIds = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        // Best-effort cleanup: this test writes to the real dev DB (no
        // transactional test bundle configured), so remove what it created.
        $conn = $this->em->getConnection();
        if (isset($this->cleanupIds['adjustment'])) {
            $conn->executeStatement('DELETE FROM sylius_adjustment WHERE order_item_id = ?', [$this->cleanupIds['order_item']]);
        }
        foreach (['order_item' => 'sylius_order_item', 'order' => 'sylius_order', 'variant' => 'sylius_product_variant', 'product' => 'sylius_product', 'tax_rate' => 'sylius_tax_rate', 'tax_category' => 'sylius_tax_category'] as $key => $table) {
            if (isset($this->cleanupIds[$key])) {
                $conn->executeStatement("DELETE FROM {$table} WHERE id = ?", [$this->cleanupIds[$key]]);
            }
        }

        parent::tearDown();
    }

    public function testReimportedVariantTaxCategoryIsPickedUpOnSecondRun(): void
    {
        $marker = uniqid('apply_taxes_test_');

        $reduced = $this->persistTaxCategory("{$marker}_REDUCED", "{$marker}_REDUCED_RATE", 0.055);
        $intermediary = $this->persistTaxCategory("{$marker}_INTERMEDIARY", "{$marker}_INTERMEDIARY_RATE", 0.10);

        $product = new Product();
        $product->setCode($marker);
        $product->setCurrentLocale('fr');
        $product->setFallbackLocale('fr');
        $product->setName('Test menu');
        $product->setSlug($marker);
        $this->em->persist($product);

        $variant = new ProductVariant();
        $variant->setCode("{$marker}_variant");
        $variant->setCurrentLocale('fr');
        $variant->setFallbackLocale('fr');
        $variant->setPrice(690);
        $variant->setTaxCategory($reduced); // as if imported before the tax-mapping fix
        $product->addVariant($variant);
        $this->em->persist($variant);

        $order = new Order();
        $order->setState('new');
        $order->setCreatedAt(new \DateTime('-1 hour'));
        $this->em->persist($order);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(690);
        $order->addItem($item);
        $this->em->persist($item);

        $this->em->flush();

        $this->cleanupIds = [
            'order_item' => $item->getId(),
            'order' => $order->getId(),
            'variant' => $variant->getId(),
            'product' => $product->getId(),
            'tax_rate' => null, // deleted via category cascade below
            'tax_category' => null,
        ];

        // First run: variant is still on the reduced rate, matches what a
        // faulty import would have produced.
        $this->runApplyTaxesCommand();

        $firstRunOriginCode = $this->fetchTaxAdjustmentOriginCode($item->getId());
        $this->assertSame("{$marker}_REDUCED_RATE", $firstRunOriginCode);

        // Simulate the catalog re-import: only the variant's tax category
        // changes, exactly like ZeltyMenuMapper::importMenuVariant() does.
        $this->em->clear();
        $variant = $this->em->getRepository(ProductVariant::class)->find($variant->getId());
        $intermediary = $this->em->getRepository(TaxCategory::class)->find($intermediary->getId());
        $variant->setTaxCategory($intermediary);
        $this->em->flush();
        $this->em->clear();

        // Second run: this is the exact "ran the command again" step reported.
        $this->runApplyTaxesCommand();

        $secondRunOriginCode = $this->fetchTaxAdjustmentOriginCode($item->getId());
        $this->assertSame(
            "{$marker}_INTERMEDIARY_RATE",
            $secondRunOriginCode,
            'The order item adjustment should follow the variant\'s current tax category on re-run.'
        );

        $rowCount = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM sylius_adjustment WHERE order_item_id = ? AND type = ?',
            [$item->getId(), 'tax']
        );
        $this->assertSame(1, $rowCount, 'Re-running should replace the stale adjustment, not add a second one next to it.');

        // Extend cleanup with the categories/rates created above.
        $conn = $this->em->getConnection();
        $conn->executeStatement('DELETE FROM sylius_adjustment WHERE order_item_id = ?', [$this->cleanupIds['order_item']]);
        $conn->executeStatement('DELETE FROM sylius_order_item WHERE id = ?', [$this->cleanupIds['order_item']]);
        $conn->executeStatement('DELETE FROM sylius_order WHERE id = ?', [$this->cleanupIds['order']]);
        $conn->executeStatement('DELETE FROM sylius_product_variant WHERE id = ?', [$this->cleanupIds['variant']]);
        $conn->executeStatement('DELETE FROM sylius_product WHERE id = ?', [$this->cleanupIds['product']]);
        $conn->executeStatement("DELETE FROM sylius_tax_rate WHERE code LIKE ?", ["{$marker}%"]);
        $conn->executeStatement("DELETE FROM sylius_tax_category WHERE code LIKE ?", ["{$marker}%"]);
        $this->cleanupIds = [];
    }

    /**
     * Answers: does re-running the command retroactively apply VAT
     * ventilation to a menu order placed *before* ZeltyMenuVatVentilator
     * existed? Yes — ventilation reads live from the order item's persisted
     * variant/option-value selections and the dishes' *current* catalog
     * state, so replaying it against an old order works the same as
     * against a fresh one. The one caveat: it uses today's dish prices/
     * categories for the ratio, not whatever they were when the order was
     * placed — same limitation the whole "recompute" command already had
     * before ventilation existed.
     */
    public function testReprocessingAnOldOrderAppliesVatVentilationRetroactively(): void
    {
        $marker = uniqid('apply_taxes_ventilation_test_');

        $food = $this->persistTaxCategory("{$marker}_FOOD", "{$marker}_FOOD_RATE", 0.10);
        $alcohol = $this->persistTaxCategory("{$marker}_ALCOHOL", "{$marker}_ALCOHOL_RATE", 0.20);

        $burger = $this->persistDish("{$marker}_ZD_BURGER", $food, 700);
        $beer = $this->persistDish("{$marker}_ZD_BEER", $alcohol, 500);

        $burgerOption = new ProductOption();
        $burgerOption->setCode("{$marker}_OPT_BURGER");
        $burgerOption->setPosition(0);
        $burgerOption->setAdditional(false);
        $this->em->persist($burgerOption);

        $burgerValue = new ProductOptionValue();
        $burgerValue->setCode("{$marker}_OPT_BURGER_V");
        $burgerValue->setOption($burgerOption);
        $burgerValue->setZeltyId($burger->getCode());
        $this->em->persist($burgerValue);

        $beerOption = new ProductOption();
        $beerOption->setCode("{$marker}_OPT_BEER");
        $beerOption->setPosition(0);
        $beerOption->setAdditional(false);
        $this->em->persist($beerOption);

        $beerValue = new ProductOptionValue();
        $beerValue->setCode("{$marker}_OPT_BEER_V");
        $beerValue->setOption($beerOption);
        $beerValue->setZeltyId($beer->getCode());
        $this->em->persist($beerValue);

        $menuProduct = new Product();
        $menuProduct->setCode("ZM{$marker}");
        $menuProduct->setZeltyId("ZM{$marker}");
        $menuProduct->setCurrentLocale('fr');
        $menuProduct->setFallbackLocale('fr');
        $menuProduct->setName('Test menu');
        $menuProduct->setSlug("zm-{$marker}");
        $this->em->persist($menuProduct);

        $menuVariant = new ProductVariant();
        $menuVariant->setCode("ZM{$marker}_variant");
        $menuVariant->setCurrentLocale('fr');
        $menuVariant->setFallbackLocale('fr');
        $menuVariant->setPrice(1200);
        $menuVariant->setTaxCategory($alcohol); // as ZeltyMenuMapper's highest-rate heuristic would have set it
        $menuVariant->addOptionValue($burgerValue);
        $menuVariant->addOptionValue($beerValue);
        $menuProduct->addVariant($menuVariant);
        $this->em->persist($menuVariant);

        // An order placed well before this feature existed.
        $order = new Order();
        $order->setState('fulfilled');
        $order->setCreatedAt(new \DateTime('-30 days'));
        $this->em->persist($order);

        $item = new OrderItem();
        $item->setVariant($menuVariant);
        $item->setUnitPrice(1200);
        $order->addItem($item);
        $this->em->persist($item);

        // getTotal() (what ventilate() splits) is derived from OrderItemUnit
        // rows, not directly from unitPrice — without this, the item's total
        // stays 0 and every slice below would too.
        self::getContainer()->get('sylius.factory.order_item_unit')->createForItem($item);

        $this->em->flush();

        $orderId = $order->getId();
        $itemId = $item->getId();
        $menuVariantId = $menuVariant->getId();
        $menuProductId = $menuProduct->getId();

        // Simulate what an *actual* historical order looks like: a single
        // adjustment at the menu's old highest-rate category, exactly what
        // ApplyTaxesCommand would have produced before ventilation existed.
        // (A fresh in-memory OrderItem has no adjustments yet regardless of
        // its createdAt, so this has to be inserted explicitly to represent
        // "already processed, long ago".)
        $conn = $this->em->getConnection();
        $conn->executeStatement(
            "INSERT INTO sylius_adjustment (order_id, order_item_id, type, label, amount, is_neutral, is_locked, origin_code, details, created_at, updated_at)
             VALUES (?, ?, 'tax', 'stale', 200, true, false, ?, '[]', now(), now())",
            [$orderId, $itemId, "{$marker}_ALCOHOL_RATE"]
        );

        $this->em->clear();

        // This is exactly "relaunch ApplyTaxesCommand" against that
        // pre-existing, already-processed order.
        $this->runApplyTaxesCommand();

        $afterRows = $this->fetchTaxAdjustmentRows($itemId);
        $this->assertCount(2, $afterRows, 'Re-running should replace the old single-category row with two rate-specific ones.');

        $amountsByOrigin = [];
        foreach ($afterRows as $row) {
            $amountsByOrigin[$row['origin_code']] = (int) $row['amount'];
        }

        $this->assertArrayHasKey("{$marker}_FOOD_RATE", $amountsByOrigin);
        $this->assertArrayHasKey("{$marker}_ALCOHOL_RATE", $amountsByOrigin);
        $this->assertSame(1200, array_sum($amountsByOrigin));

        // Strictly less tax than "the whole 1200 at 20%" (= 200) would give —
        // proving the beer's rate no longer applies to the whole bundle.
        $this->assertLessThan(200, array_sum($amountsByOrigin));

        $conn = $this->em->getConnection();
        $conn->executeStatement('DELETE FROM sylius_adjustment WHERE order_item_id = ?', [$itemId]);
        $conn->executeStatement('DELETE FROM sylius_product_variant_option_value WHERE variant_id = ?', [$menuVariantId]);
        $conn->executeStatement('DELETE FROM sylius_order_item_unit WHERE order_item_id = ?', [$itemId]);
        $conn->executeStatement('DELETE FROM sylius_order_item WHERE id = ?', [$itemId]);
        $conn->executeStatement('DELETE FROM sylius_order WHERE id = ?', [$orderId]);
        $conn->executeStatement('DELETE FROM sylius_product_variant WHERE id = ?', [$menuVariantId]);
        $conn->executeStatement('DELETE FROM sylius_product WHERE id = ?', [$menuProductId]);
        $conn->executeStatement('DELETE FROM sylius_product_option_value WHERE code LIKE ?', ["{$marker}%"]);
        $conn->executeStatement('DELETE FROM sylius_product_option WHERE code LIKE ?', ["{$marker}%"]);
        $conn->executeStatement('DELETE FROM sylius_product_variant WHERE code LIKE ?', ["{$marker}%"]);
        $conn->executeStatement('DELETE FROM sylius_product WHERE code LIKE ?', ["{$marker}%"]);
        $conn->executeStatement('DELETE FROM sylius_tax_rate WHERE code LIKE ?', ["{$marker}%"]);
        $conn->executeStatement('DELETE FROM sylius_tax_category WHERE code LIKE ?', ["{$marker}%"]);
    }

    private function persistDish(string $code, TaxCategory $category, int $price): Product
    {
        $product = new Product();
        $product->setCode($code);
        $product->setZeltyId($code);
        $product->setCurrentLocale('fr');
        $product->setFallbackLocale('fr');
        $product->setName($code);
        $product->setSlug(strtolower($code));
        $this->em->persist($product);

        $variant = new ProductVariant();
        $variant->setCode("{$code}_variant");
        $variant->setCurrentLocale('fr');
        $variant->setFallbackLocale('fr');
        $variant->setPrice($price);
        $variant->setTaxCategory($category);
        $product->addVariant($variant);
        $this->em->persist($variant);

        return $product;
    }

    private function fetchTaxAdjustmentRows(int $orderItemId): array
    {
        $this->em->clear();

        return $this->em->getConnection()->fetchAllAssociative(
            'SELECT origin_code, amount FROM sylius_adjustment WHERE order_item_id = ? AND type = ?',
            [$orderItemId, 'tax']
        );
    }

    private function persistTaxCategory(string $categoryCode, string $rateCode, float $amount): TaxCategory
    {
        $category = new TaxCategory();
        $category->setCode($categoryCode);
        $category->setName($categoryCode);
        $category->setCreatedAt(new \DateTime());
        $this->em->persist($category);

        $rate = new TaxRate();
        $rate->setCode($rateCode);
        $rate->setName($rateCode);
        $rate->setAmount($amount);
        $rate->setIncludedInPrice(true);
        $rate->setCalculator('default');
        $rate->setCountry('fr');
        $rate->setCategory($category);
        $rate->setCreatedAt(new \DateTime());
        $this->em->persist($rate);

        $this->em->flush();

        return $category;
    }

    private function runApplyTaxesCommand(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('coopcycle:orders:process-taxes');
        $tester = new CommandTester($command);
        $tester->execute([]);
    }

    private function fetchTaxAdjustmentOriginCode(int $orderItemId): ?string
    {
        $this->em->clear();

        return $this->em->getConnection()->fetchOne(
            'SELECT origin_code FROM sylius_adjustment WHERE order_item_id = ? AND type = ? ORDER BY id DESC LIMIT 1',
            [$orderItemId, 'tax']
        ) ?: null;
    }
}
