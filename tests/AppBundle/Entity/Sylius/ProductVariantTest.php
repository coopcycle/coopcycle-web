<?php

namespace Tests\AppBundle\Entity\Sylius;

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
}
