<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Spreadsheet\ProductSpreadsheetParser;
use AppBundle\Utils\TaskSpreadsheetParser;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Oneup\UploaderBundle\Uploader\File\FlysystemFile;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Taxation\Model\TaxCategory;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class ProductSpreadsheetParserTest extends KernelTestCase
{
    use ProphecyTrait;

    private $filesystem;
    private $taxCategoryRepository;
    private $parser;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $adapter = new LocalFilesystemAdapter(__DIR__ . '/../Resources/spreadsheet');
        $this->filesystem = new Filesystem($adapter);

        $serializer = self::$container->get(SerializerInterface::class);
        $productFactory = self::$container->get('sylius.factory.product');
        $variantFactory = self::$container->get('sylius.factory.product_variant');
        $this->taxCategoryRepository = $this->prophesize(TaxCategoryRepositoryInterface::class);

        $baseStandard = new TaxCategory();
        $baseStandard->setCode('BASE_STANDARD');
        $baseStandard->setName('tax_category.base_standard');

        $baseIntermediary = new TaxCategory();
        $baseIntermediary->setCode('BASE_INTERMEDIARY');
        $baseIntermediary->setName('tax_category.base_intermediary');

        $this->taxCategoryRepository->findAll()
            ->willReturn([
                $baseStandard,
                $baseIntermediary,
            ]);

        $this->taxCategoryRepository->findOneBy(['name' => 'tax_category.base_standard'])
            ->willReturn($baseStandard);

        $this->taxCategoryRepository->findOneBy(['name' => 'tax_category.base_intermediary'])
            ->willReturn($baseIntermediary);

        $this->parser = new ProductSpreadsheetParser(
            $serializer,
            $productFactory,
            $variantFactory,
            $this->taxCategoryRepository->reveal()
        );
    }

    public function testValidCsv()
    {
        $foodTax = new TaxCategory();

        $this->taxCategoryRepository->findOneBy(['name' => 'Food'])
            ->willReturn($foodTax);

        $file = new FlysystemFile('products.csv', $this->filesystem);
        $products = $this->parser->parse($file);

        $this->assertCount(2, $products);

        $this->assertEquals('Pizza Margherita', $products[0]->getName());
        $this->assertTrue($products[0]->hasVariants());
        $this->assertEquals(900, $products[0]->getVariants()->first()->getPrice());
        $this->assertEquals($foodTax, $products[0]->getVariants()->first()->getTaxCategory());
    }

    public function testMissingPriceColumn()
    {
        $this->markTestSkipped();

        $this->expectExceptionMessage('You must provide a "price_tax_incl" column');

        $foodTax = new TaxCategory();

        $this->taxCategoryRepository->findOneBy(['name' => 'Food'])
            ->willReturn($foodTax);

        $file = new FlysystemFile('products_missing_price.csv', $this->filesystem);
        $products = $this->parser->parse($file);
    }

    public function testUnknownTaxCategory()
    {
        $this->expectExceptionMessage('The tax category "Foobar" does not exist. Valid values are "Food", "Drink".');

        $foodTax = new TaxCategory();
        $foodTax->setName('Food');

        $drinkTax = new TaxCategory();
        $drinkTax->setName('Drink');

        $this->taxCategoryRepository->findAll()
            ->willReturn([$foodTax, $drinkTax]);
        $this->taxCategoryRepository->findOneBy(['name' => 'Food'])
            ->willReturn($foodTax);
        $this->taxCategoryRepository->findOneBy(['name' => 'Drink'])
            ->willReturn($foodTax);
        $this->taxCategoryRepository->findOneBy(['name' => 'Foobar'])
            ->willReturn(null);

        $file = new FlysystemFile('products_unknown_tax_category.csv', $this->filesystem);
        $products = $this->parser->parse($file);
    }

    public function testExampleData()
    {
        $categories = $this->taxCategoryRepository->reveal()->findAll();
        $names = array_map(fn($category) => $category->getName(), $categories);

        $data = $this->parser->getExampleData();

        $this->assertCount(2, $data);

        foreach ($data as $row) {
            $this->assertContains($row['tax_category'], $names);
        }
    }

    public function testCanParseExampleData()
    {
        $results = $this->parser->parseData($this->parser->getExampleData());

        $this->assertNotEmpty($results);
    }
}
