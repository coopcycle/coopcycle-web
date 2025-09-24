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
        $this->assertEquals('Plus de 5.00 km', $this->humanizer->humanize($rule));

        $rule->setExpression('distance in 3000..5000');
        $this->assertEquals('Entre 3.00 km et 5.00 km', $this->humanizer->humanize($rule));
    }

    public function testWeight()
    {
        $rule = new PricingRule();

        $rule->setExpression('weight > 5000');
        $this->assertEquals('Plus de 5.00 kg', $this->humanizer->humanize($rule));

        $rule->setExpression('weight in 3000..5000');
        $this->assertEquals('Entre 3.00 kg et 5.00 kg', $this->humanizer->humanize($rule));
    }

    public function testInZone()
    {
        $rule = new PricingRule();
        $rule->setExpression('in_zone(dropoff.address, "south")');

        $this->assertEquals('Adresse dropoff dans zone "south"', $this->humanizer->humanize($rule));
    }

    public function testInZoneOutZone()
    {
        $rule = new PricingRule();
        $rule->setExpression('in_zone(pickup.address, "south") and out_zone(dropoff.address, "north") and weight > 5000');

        $this->assertEquals('Adresse pickup dans zone "south", adresse dropoff hors zone "north", plus de 5.00 kg', $this->humanizer->humanize($rule));
    }

    public function testPricePerPackage()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.containsAtLeastOne("Bouquet L")');
        $rule->setPrice('price_per_package(packages, "Bouquet L", 100, 0, 100)');

         $this->assertEquals('packages.containsAtLeastOne("Bouquet L")', $this->humanizer->humanize($rule));
         //TODO
//        $this->assertEquals('1€ per Bouquet L', $this->humanizer->humanize($rule));
    }

    public function testPriceRange()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance > 5000');
        $rule->setPrice('price_range(distance, 100, 1000, 2000)');

         $this->assertEquals('Plus de 5.00 km', $this->humanizer->humanize($rule));
         //TODO
//         $this->assertEquals('1€ per km above 2km', $this->humanizer->humanize($rule));
    }

    public function testTaskType()
    {
        $rule = new PricingRule();

        $rule->setExpression('task.type == "PICKUP"');
        $this->assertEquals('Taux de retrait', $this->humanizer->humanize($rule));

        $rule->setExpression('task.type == "DROPOFF"');
        $this->assertEquals('Taux de dépôt', $this->humanizer->humanize($rule));
    }

    public function testTimeRangeLength()
    {
        $rule = new PricingRule();
        $rule->setExpression('time_range_length(dropoff, \'hours\', \'< 1.5\')');

        $this->assertEquals('Créneau horaire de dépôt moins de 1.5 heure', $this->humanizer->humanize($rule));
    }

    public function testTimeRangeLengthIn()
    {
        $rule = new PricingRule();
        $rule->setExpression('time_range_length(dropoff, \'hours\', \'in 1..2\')');

        $this->assertEquals('Créneau horaire de dépôt entre 1 heure et 2 heures ', $this->humanizer->humanize($rule));
    }

    public function testDiffHours()
    {
        $rule = new PricingRule();
        $rule->setExpression('diff_hours(pickup, \'< 12\')');

        $this->assertEquals('Délai de préavis pour retrait moins de 12 heures', $this->humanizer->humanize($rule));
    }

    public function testDiffDays()
    {
        $rule = new PricingRule();
        $rule->setExpression('diff_days(pickup, \'> 2\')');

        $this->assertEquals('Délai de préavis pour retrait plus de 2 jours', $this->humanizer->humanize($rule));
    }

    public function testDiffDaysIn()
    {
        $rule = new PricingRule();
        $rule->setExpression('diff_days(pickup, \'in 1..2\')');

        $this->assertEquals('Délai de préavis pour retrait entre 1 jour et 2 jours ', $this->humanizer->humanize($rule));
    }

    public function testOrderItemsTotal()
    {
        $rule = new PricingRule();
        $rule->setExpression('order.itemsTotal > 20');

        $this->assertEquals('Total du panier plus de 20', $this->humanizer->humanize($rule));
    }

    public function testPackagesTotalVolumeUnits()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.totalVolumeUnits() < 5');

        $this->assertEquals('Volume du colis moins de 5', $this->humanizer->humanize($rule));
    }

    public function testAnd()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.containsAtLeastOne("📦 - A eurobox/fruitbox of documents") and task.type == "DROPOFF"');

        $this->assertEquals('packages.containsAtLeastOne("📦 - A eurobox/fruitbox of documents") and task.type == "DROPOFF"', $this->humanizer->humanize($rule));
    }

    public function testTimeSlotWithTaskType()
    {
        $rule = new PricingRule();
        $rule->setExpression('time_slot == "/api/time_slots/1" and task.type == "PICKUP"');

        $this->assertEquals('/api/time_slots/1, taux de retrait', $this->humanizer->humanize($rule));
    }
}
