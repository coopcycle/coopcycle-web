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
        $this->translator = self::$container->get('translator');

        $this->humanizer = new RuleHumanizer($this->expressionLanguage, $this->translator);
    }

    public function testDistance()
    {
        $rule = new PricingRule();

        $rule->setExpression('distance > 5000');
        $this->assertEquals('plus de 5.00 km', $this->humanizer->humanize($rule));

        $rule->setExpression('distance in 3000..5000');
        $this->assertEquals('entre 3.00 km et 5.00 km', $this->humanizer->humanize($rule));
    }

    public function testWeight()
    {
        $rule = new PricingRule();

        $rule->setExpression('weight > 5000');
        $this->assertEquals('plus de 5.00 kg', $this->humanizer->humanize($rule));

        $rule->setExpression('weight in 3000..5000');
        $this->assertEquals('entre 3.00 kg et 5.00 kg', $this->humanizer->humanize($rule));
    }

    public function testInZone()
    {
        $rule = new PricingRule();
        $rule->setExpression('in_zone(dropoff.address, "south")');

        $this->assertEquals('adresse dropoff dans zone "south"', $this->humanizer->humanize($rule));
    }

    public function testInZoneOutZone()
    {
        $rule = new PricingRule();
        $rule->setExpression('in_zone(pickup.address, "south") and out_zone(dropoff.address, "north") and weight > 5000');

        $this->assertEquals('adresse pickup dans zone "south", adresse dropoff hors zone "north", plus de 5.00 kg', $this->humanizer->humanize($rule));
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
