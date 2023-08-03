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
        $rule->setExpression('distance in 3000..5000');

        $this->assertEquals('distance between 3.00 km and 5.00 km', $this->humanizer->humanize($rule));
    }

    public function testInZone()
    {
    	$rule = new PricingRule();
        $rule->setExpression('in_zone(dropoff.address, "south")');

        $this->assertEquals('dropoff address in zone "south"', $this->humanizer->humanize($rule));
    }

    public function testInZoneOutZone()
    {
    	$rule = new PricingRule();
        $rule->setExpression('in_zone(pickup.address, "south") and out_zone(dropoff.address, "north")');

        $this->assertEquals('pickup address in zone "south", dropoff address in zone "north"', $this->humanizer->humanize($rule));
    }
}
