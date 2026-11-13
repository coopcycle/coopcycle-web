<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Spreadsheet\ProductDataExporter;
use League\Csv\Reader as CsvReader;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Sylius\Component\Taxation\Model\TaxCategory;

class ProductDataExporterTest extends TestCase
{
    use ProphecyTrait;

    private $variantResolver;
    private $exporter;

    public function setUp(): void
    {
        $this->variantResolver = $this->prophesize(ProductVariantResolverInterface::class);

        $this->exporter = new ProductDataExporter($this->variantResolver->reveal());
    }

    private function createProduct(string $name, ?string $description = null): Product
    {
        $product = new Product();
        $product->setCurrentLocale('en');
        $product->setName($name);
        $product->setDescription($description);

        return $product;
    }

    private function createVariant(int $price, string $taxCategoryName): ProductVariant
    {
        $taxCategory = new TaxCategory();
        $taxCategory->setName($taxCategoryName);

        $variant = new ProductVariant();
        $variant->setPrice($price);
        $variant->setTaxCategory($taxCategory);

        return $variant;
    }

    private function readRecords(string $csv): array
    {
        $reader = CsvReader::fromString($csv);
        $reader->setHeaderOffset(0);

        return [$reader->getHeader(), iterator_to_array($reader->getRecords(), false)];
    }

    public function testExport()
    {
        $margherita = $this->createProduct('Pizza Margherita', 'The most famous pizza');
        $regina = $this->createProduct('Pizza Regina');

        $this->variantResolver->getVariant($margherita)
            ->willReturn($this->createVariant(900, 'tax_category.base_standard'));
        $this->variantResolver->getVariant($regina)
            ->willReturn($this->createVariant(1000, 'tax_category.base_intermediary'));

        [$header, $records] = $this->readRecords($this->exporter->export([$margherita, $regina]));

        $this->assertEquals(['name', 'description', 'price_tax_incl', 'tax_category'], $header);
        $this->assertEquals([
            [
                'name' => 'Pizza Margherita',
                'description' => 'The most famous pizza',
                'price_tax_incl' => '900',
                'tax_category' => 'tax_category.base_standard',
            ],
            [
                'name' => 'Pizza Regina',
                'description' => '',
                'price_tax_incl' => '1000',
                'tax_category' => 'tax_category.base_intermediary',
            ],
        ], $records);
    }

    public function testExportEscapesValues()
    {
        $product = $this->createProduct('Pizza "4 fromaggi", large', "Mozzarella, gorgonzola\nparmigiano, fontina");

        $this->variantResolver->getVariant($product)
            ->willReturn($this->createVariant(1350, 'tax_category.base_standard'));

        [, $records] = $this->readRecords($this->exporter->export([$product]));

        $this->assertCount(1, $records);
        $this->assertEquals('Pizza "4 fromaggi", large', $records[0]['name']);
        $this->assertEquals("Mozzarella, gorgonzola\nparmigiano, fontina", $records[0]['description']);
    }

    public function testExportWithoutVariant()
    {
        $product = $this->createProduct('Pizza Margherita');

        $this->variantResolver->getVariant($product)->willReturn(null);

        [, $records] = $this->readRecords($this->exporter->export([$product]));

        $this->assertEquals([
            [
                'name' => 'Pizza Margherita',
                'description' => '',
                'price_tax_incl' => '',
                'tax_category' => '',
            ],
        ], $records);
    }

    public function testExportWithoutProducts()
    {
        [$header, $records] = $this->readRecords($this->exporter->export([]));

        $this->assertEquals(['name', 'description', 'price_tax_incl', 'tax_category'], $header);
        $this->assertEmpty($records);
    }
}
