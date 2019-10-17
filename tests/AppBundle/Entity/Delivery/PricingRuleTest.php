<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class PricingRuleTest extends TestCase
{
    public function testMatches()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance in 0..3000 and weight in 0..1000');

        $delivery = new Delivery();

        $delivery->setDistance(1500);
        $delivery->setWeight(500);
        $this->assertTrue($rule->matches($delivery));

        $delivery->setDistance(3500);
        $delivery->setWeight(500);
        $this->assertFalse($rule->matches($delivery));

        $delivery->setDistance(1500);
        $delivery->setWeight(1500);
        $this->assertFalse($rule->matches($delivery));
    }

    public function testNullVariable()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance in 500..3000');

        $delivery = new Delivery();
        $delivery->setDistance(null);

        $this->assertFalse($rule->matches($delivery));
    }

    public function testUnknownVariable()
    {
        $this->expectException(SyntaxError::class);

        $rule = new PricingRule();
        $rule->setExpression('foo == 1');

        $delivery = new Delivery();
        $delivery->setDistance(null);

        $this->assertFalse($rule->matches($delivery));
    }

    public function testEvaluatePrice()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance in 500..3000');
        $rule->setPrice('1500 + ((distance / 1000) * 300)');

        $delivery = new Delivery();
        $delivery->setDistance(2500);

        $this->assertTrue($rule->matches($delivery));

        // 15€ + 3€ per km = 15 + 2,5 * 3
        $this->assertEquals(2250, $rule->evaluatePrice($delivery));

        $rule = new PricingRule();
        $rule->setPrice('700 + (packages.quantity("M") * 250)');

        $mediumPackage = new Package();
        $mediumPackage->setName('M');

        $delivery = new Delivery();
        $delivery->addPackageWithQuantity($mediumPackage, 1);

        // 7€ + 2,5€ per package
        $this->assertEquals(950, $rule->evaluatePrice($delivery));
    }
}
