<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\ExpressionLanguage\DeliveryExpressionLanguageVisitor;
use AppBundle\ExpressionLanguage\PriceEvaluation;
use AppBundle\ExpressionLanguage\PricePercentageExpressionLanguageProvider;
use AppBundle\ExpressionLanguage\PricePerPackageExpressionLanguageProvider;
use AppBundle\ExpressionLanguage\PriceRangeExpressionLanguageProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class PricingRuleTest extends TestCase
{

    private function toExpressionLanguageValues(Delivery $delivery): array
    {
        $deliveryExpressionLanguageVisitor = new DeliveryExpressionLanguageVisitor();
        return $deliveryExpressionLanguageVisitor->toExpressionLanguageValues($delivery);
    }

    private function apply(PricingRule $rule, array $values, ?ExpressionLanguage $language = null): int
    {
        $result = $rule->apply($values, $language);

        $total = 0;

        if (is_array($result)) {
            foreach ($result as $item) {
                $total += $item->unitPrice * $item->quantity;
            }
        } elseif ($result instanceof PriceEvaluation) {
            $total = $result->unitPrice * $result->quantity;
        } else {
            $total = $result;
        }

        return $total;
    }

    public function testMatches()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance in 0..3000 and weight in 0..1000');

        $delivery = new Delivery();

        $delivery->setDistance(1500);
        $delivery->setWeight(500);
        $this->assertTrue($rule->matches($this->toExpressionLanguageValues($delivery)));

        $delivery->setDistance(3500);
        $delivery->setWeight(500);
        $this->assertFalse($rule->matches($this->toExpressionLanguageValues($delivery)));

        $delivery->setDistance(1500);
        $delivery->setWeight(1500);
        $this->assertFalse($rule->matches($this->toExpressionLanguageValues($delivery)));
    }

    public function testNullVariable()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance in 500..3000');

        $delivery = new Delivery();
        $delivery->setDistance(null);

        $this->assertFalse($rule->matches($this->toExpressionLanguageValues($delivery)));
    }

    public function testUnknownVariable()
    {
        $this->expectException(SyntaxError::class);

        $rule = new PricingRule();
        $rule->setExpression('foo == 1');

        $delivery = new Delivery();
        $delivery->setDistance(null);

        $this->assertFalse($rule->matches($this->toExpressionLanguageValues($delivery)));
    }

    public function testEvaluateDistanceBasedExpression()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance in 500..3000');
        //FIXME: where could we create such a rule? Does it test some legacy format?
        $rule->setPrice('1500 + ((distance / 1000) * 300)');

        $delivery = new Delivery();
        $delivery->setDistance(2500);

        $this->assertTrue($rule->matches($this->toExpressionLanguageValues($delivery)));

        // 15€ + 3€ per km = 15 + 2,5 * 3
        $this->assertEquals(2250, $this->apply($rule, $this->toExpressionLanguageValues($delivery)));
    }

    public function testEvaluatePackageBasedExpression()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.containsAtLeastOne("M")');
        //FIXME: where could we create such a rule? Does it test some legacy format?
        $rule->setPrice('700 + (packages.quantity("M") * 250)');

        $mediumPackage = new Package();
        $mediumPackage->setName('M');

        $delivery = new Delivery();
        $delivery->addPackageWithQuantity($mediumPackage, 1);

        // 7€ + 2,5€ per package
        $this->assertEquals(950, $this->apply($rule, $this->toExpressionLanguageValues($delivery)));
    }

    public function testEvaluateFixedPriceExpression()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance in 500..3000');
        $rule->setPrice('1500');

        $delivery = new Delivery();
        $delivery->setDistance(2500);

        $this->assertTrue($rule->matches($this->toExpressionLanguageValues($delivery)));

        // 15€
        $this->assertEquals(1500, $this->apply($rule, $this->toExpressionLanguageValues($delivery)));
    }

    public function testEvaluatePricePercentageExpression()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance in 500..3000');
        $rule->setPrice('price_percentage(11500)');

        $delivery = new Delivery();
        $delivery->setDistance(2500);

        $this->assertTrue($rule->matches($this->toExpressionLanguageValues($delivery)));

        $language = new ExpressionLanguage();
        $language->registerProvider(new PricePercentageExpressionLanguageProvider());

        // +15%
        $this->assertEquals(11500, $this->apply($rule, $this->toExpressionLanguageValues($delivery), $language));
    }

    public function testEvaluatePriceRangeExpression()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance in 500..3000');
        $rule->setPrice('price_range(distance, 100, 1000, 0)');

        $delivery = new Delivery();
        $delivery->setDistance(2500);

        $this->assertTrue($rule->matches($this->toExpressionLanguageValues($delivery)));

        $language = new ExpressionLanguage();
        $language->registerProvider(new PriceRangeExpressionLanguageProvider());


        // 1€ per km = 3
        $this->assertEquals(300, $this->apply($rule, $this->toExpressionLanguageValues($delivery), $language));
    }

    public function testEvaluatePricePerPackageExpression()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.containsAtLeastOne("M")');
        $rule->setPrice('price_per_package(packages, "M", 250, 0, 0)');

        $mediumPackage = new Package();
        $mediumPackage->setName('M');

        $delivery = new Delivery();
        $delivery->addPackageWithQuantity($mediumPackage, 1);

        $language = new ExpressionLanguage();
        $language->registerProvider(new PricePerPackageExpressionLanguageProvider());

        // 2,5€ per package
        $this->assertEquals(250, $this->apply($rule, $this->toExpressionLanguageValues($delivery), $language));
    }

    public function testGetNameReturnsProductOptionName()
    {
        $productOption = new ProductOption();
        $productOption->setCurrentLocale('en');
        $productOption->setName('Pricing Rules');

        $productOptionValue = new ProductOptionValue();
        $productOptionValue->setCurrentLocale('en');
        $productOptionValue->setValue('Express Delivery');
        $productOptionValue->setOption($productOption);

        $rule = new PricingRule();
        $rule->setProductOptionValue($productOptionValue);

        $this->assertEquals('Express Delivery', $rule->getName());
    }

    public function testGetNameReturnsNullWhenNoProductOption()
    {
        $rule = new PricingRule();

        $this->assertNull($rule->getName());
    }
}
