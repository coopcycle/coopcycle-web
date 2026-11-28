<?php

namespace Tests\AppBundle\Entity\Sylius;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ProductVariantTest extends TestCase
{
    use ProphecyTrait;

    public function testAddOptionValueWithQuantity()
    {
        $variant = new ProductVariant();

        $optionValue = $this->prophesize(ProductOptionValueInterface::class);
        $variant->addOptionValueWithQuantity($optionValue->reveal(), 0);
        $this->assertFalse($variant->hasOptionValue($optionValue->reveal()));

        $otherOptionValue = $this->prophesize(ProductOptionValueInterface::class);
        $variant->addOptionValueWithQuantity($otherOptionValue->reveal(), 2);
        $this->assertTrue($variant->hasOptionValue($otherOptionValue->reveal()));
        $this->assertEquals(2, $variant->getQuantityForOptionValue($otherOptionValue->reveal()));

        $variant->addOptionValueWithQuantity($otherOptionValue->reveal(), 3);
        $this->assertTrue($variant->hasOptionValue($otherOptionValue->reveal()));
        $this->assertEquals(3, $variant->getQuantityForOptionValue($otherOptionValue->reveal()));
    }

    public function testGetQuantityForOptionValue()
    {
        $variant = new ProductVariant();

        $optionValue = $this->prophesize(ProductOptionValueInterface::class);
        $variant->addOptionValueWithQuantity($optionValue->reveal(), 2);
        $this->assertEquals(2, $variant->getQuantityForOptionValue($optionValue->reveal()));

        $otherOptionValue = $this->prophesize(ProductOptionValueInterface::class);
        $this->assertEquals(0, $variant->getQuantityForOptionValue($otherOptionValue->reveal()));
    }

    public function testHasOptionValueWithQuantity()
    {
        $variant = new ProductVariant();

        $optionValue = $this->prophesize(ProductOptionValueInterface::class);
        $variant->addOptionValueWithQuantity($optionValue->reveal(), 3);
        $this->assertTrue($variant->hasOptionValueWithQuantity($optionValue->reveal(), 3));
    }

    public function testGetWeightWithoutWeights()
    {
        $product = new Product();

        $optionValue = new ProductOptionValue();

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->addOptionValueWithQuantity($optionValue, 1);

        $this->assertNull($variant->getWeight());
    }

    public function testGetWeightFromProduct()
    {
        $product = new Product();
        $product->setWeight(4000);

        $variant = new ProductVariant();
        $variant->setProduct($product);

        $this->assertEquals(4000, $variant->getWeight());
    }

    public function testGetWeightAddsOptionValues()
    {
        $product = new Product();
        $product->setWeight(1000);

        $large = new ProductOptionValue();
        $large->setWeight(5000);

        $softener = new ProductOptionValue();
        $softener->setWeight(250);

        // Has no weight, should be ignored
        $ironing = new ProductOptionValue();

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->addOptionValueWithQuantity($large, 1);
        $variant->addOptionValueWithQuantity($softener, 2);
        $variant->addOptionValueWithQuantity($ironing, 1);

        // 1000 + 5000 + (2 * 250)
        $this->assertEquals(6500, $variant->getWeight());
    }

    public function testGetWeightFromOptionValuesOnly()
    {
        $product = new Product();

        $large = new ProductOptionValue();
        $large->setWeight(9000);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->addOptionValueWithQuantity($large, 1);

        $this->assertEquals(9000, $variant->getWeight());
    }
}
