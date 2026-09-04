<?php

namespace Tests\AppBundle\Command;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Entity\Sylius\Product;
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
