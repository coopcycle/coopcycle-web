<?php

namespace Tests\AppBundle\Pricing;

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
        $this->expressionLanguage = self::getContainer()->get('coopcycle.expression_language');
        $this->translator = self::getContainer()->get('translator');

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

    public function testTaskType()
    {
        $rule = new PricingRule();

        $rule->setExpression('task.type == "PICKUP"');
        $this->assertEquals('taux de retrait', $this->humanizer->humanize($rule));

        $rule->setExpression('task.type == "DROPOFF"');
        $this->assertEquals('taux de dépôt', $this->humanizer->humanize($rule));
    }

    public function testTimeRangeLength()
    {
        $rule = new PricingRule();
        $rule->setExpression('time_range_length(dropoff, \'hours\', \'< 1.5\')');

        $this->assertEquals('créneau horaire de dépôt moins de 1.5 heure', $this->humanizer->humanize($rule));
    }

    public function testTimeRangeLengthIn()
    {
        $rule = new PricingRule();
        $rule->setExpression('time_range_length(dropoff, \'hours\', \'in 1..2\')');

        $this->assertEquals('créneau horaire de dépôt entre 1 heure et 2 heures ', $this->humanizer->humanize($rule));
    }

    public function testDiffHours()
    {
        $rule = new PricingRule();
        $rule->setExpression('diff_hours(pickup, \'< 12\')');

        $this->assertEquals('délai de préavis pour retrait moins de 12 heures', $this->humanizer->humanize($rule));
    }

    public function testDiffDays()
    {
        $rule = new PricingRule();
        $rule->setExpression('diff_days(pickup, \'> 2\')');

        $this->assertEquals('délai de préavis pour retrait plus de 2 jours', $this->humanizer->humanize($rule));
    }

    public function testDiffDaysIn()
    {
        $rule = new PricingRule();
        $rule->setExpression('diff_days(pickup, \'in 1..2\')');

        $this->assertEquals('délai de préavis pour retrait entre 1 jour et 2 jours ', $this->humanizer->humanize($rule));
    }
    
    public function testOrderItemsTotal()
    {
        $rule = new PricingRule();
        $rule->setExpression('order.itemsTotal > 20');

        $this->assertEquals('total du panier plus de 20', $this->humanizer->humanize($rule));
    }

    public function testPackagesTotalVolumeUnits()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.totalVolumeUnits() < 5');

        $this->assertEquals('volume du colis moins de 5', $this->humanizer->humanize($rule));
    }
}
