<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Task;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

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

    /**
     * @expectedException Symfony\Component\ExpressionLanguage\SyntaxError
     */
    public function testUnknownVariable()
    {
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

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setAddress($pickupAddress);

        $dropoffAddress = new Address();
        $dropoffAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff->setAddress($dropoffAddress);

        $delivery = new Delivery();
        $delivery->setDistance(2500);
        $delivery->addTask($pickup);
        $delivery->addTask($dropoff);

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
