<?php

namespace Tests\AppBundle\Pricing;

use AppBundle\Pricing\PriceExpressions\FixedPriceExpression;
use AppBundle\Pricing\PriceExpressions\PricePercentageExpression;
use AppBundle\Pricing\PriceExpressions\PricePerPackageExpression;
use AppBundle\Pricing\PriceExpressions\PriceRangeExpression;
use AppBundle\Pricing\PriceExpressions\UnparsablePriceExpression;
use AppBundle\Pricing\PriceExpressionParser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PriceExpressionParserTest extends KernelTestCase
{
    private PriceExpressionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $expressionLanguage = self::getContainer()->get('coopcycle.expression_language');

        $this->parser = new PriceExpressionParser($expressionLanguage);
    }

    public function testParseFixedPrice(): void
    {
        $result = $this->parser->parsePrice('1053');

        $this->assertInstanceOf(FixedPriceExpression::class, $result);
        $this->assertEquals(1053, $result->value);
    }

    public function testParsePricePercentage(): void
    {
        $result = $this->parser->parsePrice('price_percentage(8500)');

        $this->assertInstanceOf(PricePercentageExpression::class, $result);
        $this->assertEquals(8500, $result->percentage);
    }

    public function testParsePriceRange(): void
    {
        $result = $this->parser->parsePrice('price_range(distance, 450, 2000, 2500)');

        $this->assertInstanceOf(PriceRangeExpression::class, $result);
        $this->assertEquals('distance', $result->attribute);
        $this->assertEquals(450, $result->price);
        $this->assertEquals(2000, $result->step);
        $this->assertEquals(2500, $result->threshold);
    }

    public function testParsePriceRangeWithTotalVolumeUnits(): void
    {
        $result = $this->parser->parsePrice('price_range(packages.totalVolumeUnits(), 100, 1, 0)');

        $this->assertInstanceOf(PriceRangeExpression::class, $result);
        $this->assertEquals('packages.totalVolumeUnits()', $result->attribute);
        $this->assertEquals(100, $result->price);
        $this->assertEquals(1, $result->step);
        $this->assertEquals(0, $result->threshold);
    }

    public function testParsePricePerPackageFunction(): void
    {
        $result = $this->parser->parsePrice('price_per_package(packages, "XXL", 1240, 3, 210)');

        $this->assertInstanceOf(PricePerPackageExpression::class, $result);
        $this->assertEquals('XXL', $result->packageName);
        $this->assertEquals(1240, $result->unitPrice);
        $this->assertEquals(3, $result->offset);
        $this->assertEquals(210, $result->discountPrice);
    }

    public function testParseRawFormula(): void
    {
        $expression = '1800 + (ceil((distance - 8000) / 1000) * 360)';
        $result = $this->parser->parsePrice($expression);

        $this->assertInstanceOf(UnparsablePriceExpression::class, $result);
        $this->assertEquals($expression, $result->expression);
    }

    public function testParseInvalidExpression(): void
    {
        $expression = 'invalid_expression_that_cannot_be_parsed';
        $result = $this->parser->parsePrice($expression);

        $this->assertInstanceOf(UnparsablePriceExpression::class, $result);
        $this->assertEquals($expression, $result->expression);
    }
}
