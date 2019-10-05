<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Task;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
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
        $rule->setPrice('15 + ((distance / 1000) * 3)');

        $delivery = new Delivery();
        $delivery->setDistance(2500);

        // 15€ + 3€ per km = 15 + 2,5 * 3
        $this->assertEquals(22.5, $rule->evaluatePrice($delivery));
    }

    public function testToExpressionLanguageValues()
    {
        $pickupAddress = new Address();
        $pickupAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $dropoffAddress = new Address();
        $dropoffAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $delivery = new Delivery();
        $delivery->setDistance(2500);
        $delivery->getPickup()->setAddress($pickupAddress);
        $delivery->getDropoff()->setAddress($dropoffAddress);

        $values = PricingRule::toExpressionLanguageValues($delivery);

        $this->assertArrayHasKey('distance', $values);
        $this->assertArrayHasKey('weight', $values);
        $this->assertArrayHasKey('vehicle', $values);
        $this->assertArrayHasKey('pickup', $values);
        $this->assertArrayHasKey('dropoff', $values);

        $language = new ExpressionLanguage();

        $this->assertEquals($pickupAddress, $language->evaluate('pickup.address', $values));
        $this->assertEquals($dropoffAddress, $language->evaluate('dropoff.address', $values));
    }
}
