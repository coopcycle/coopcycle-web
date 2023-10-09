<?php

namespace Tests\AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Pricing\RuleHumanizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RuleHumanizerTest extends KernelTestCase
{
	protected function setUp(): void
    {
    	parent::setUp();

        self::bootKernel();

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->expressionLanguage = self::$container->get('coopcycle.expression_language');

        $this->humanizer = new RuleHumanizer($this->expressionLanguage);
    }

    public function testDistance()
    {
    	$rule = new PricingRule();

    	$rule->setExpression('distance > 5000');
        $this->assertEquals('more than 5.00 km', $this->humanizer->humanize($rule));

        $rule->setExpression('distance in 3000..5000');
        $this->assertEquals('between 3.00 km and 5.00 km', $this->humanizer->humanize($rule));
    }

    public function testWeight()
    {
    	$rule = new PricingRule();

        $rule->setExpression('weight > 5000');
        $this->assertEquals('more than 5.00 kg', $this->humanizer->humanize($rule));

        $rule->setExpression('weight in 3000..5000');
        $this->assertEquals('between 3.00 kg and 5.00 kg', $this->humanizer->humanize($rule));
    }

    public function testInZone()
    {
    	$rule = new PricingRule();
        $rule->setExpression('in_zone(dropoff.address, "south")');

        $this->assertEquals('dropoff address inside zone "south"', $this->humanizer->humanize($rule));
    }

    public function testInZoneOutZone()
    {
    	$rule = new PricingRule();
        $rule->setExpression('in_zone(pickup.address, "south") and out_zone(dropoff.address, "north") and weight > 5000');

        $this->assertEquals('pickup address inside zone "south", dropoff address outside zone "north", more than 5.00 kg', $this->humanizer->humanize($rule));
    }

    public function testPricePerPackage()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.containsAtLeastOne("Bouquet L")');
        $rule->setPrice('price_per_package(packages, "Bouquet L", 100, 0, 100)');

        // $this->assertEquals('contains one package', $this->humanizer->humanize($rule));
    }

    public function testPriceRange()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance > 0');
        $rule->setPrice('price_range(distance, 100, 1000, 2000)');

        // $this->assertEquals('contains one package', $this->humanizer->humanize($rule));
    }
}
