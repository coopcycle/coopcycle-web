<?php

namespace Tests\AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Pricing\PriceExpressionParser;
use AppBundle\Pricing\RuleHumanizer;
use AppBundle\Utils\PriceFormatter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RuleHumanizerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->expressionLanguage = self::getContainer()->get('coopcycle.expression_language');
        $this->priceExpressionParser = self::getContainer()->get(PriceExpressionParser::class);
        $this->translator = self::getContainer()->get('translator');
        $this->priceFormatter = self::getContainer()->get(PriceFormatter::class);

        $this->humanizer = new RuleHumanizer(
            $this->expressionLanguage,
            $this->priceExpressionParser,
            $this->translator,
            $this->priceFormatter,
        );
    }

    public function testDistanceOpenRange()
    {
        $rule = new PricingRule();

        $rule->setExpression('distance > 5000');
        $rule->setPrice('100');
        $this->assertEquals('Plus de 5.00 km - €1.00', $this->humanizer->humanize($rule));
    }

    public function testDistanceClosedRange()
    {
        $rule = new PricingRule();

        $rule->setExpression('distance in 3000..5000');
        $rule->setPrice('100');
        $this->assertEquals('Entre 3.00 km et 5.00 km - €1.00', $this->humanizer->humanize($rule));
    }

    public function testWeight()
    {
        $rule = new PricingRule();

        $rule->setExpression('weight > 5000');
        $rule->setPrice('100');
        $this->assertEquals('Plus de 5.00 kg - €1.00', $this->humanizer->humanize($rule));

        $rule->setExpression('weight in 3000..5000');
        $rule->setPrice('100');
        $this->assertEquals('Entre 3.00 kg et 5.00 kg - €1.00', $this->humanizer->humanize($rule));
    }

    public function testInZone()
    {
        $rule = new PricingRule();
        $rule->setExpression('in_zone(dropoff.address, "south")');
        $rule->setPrice('100');

        $this->assertEquals('Adresse dropoff dans zone "south" - €1.00', $this->humanizer->humanize($rule));
    }

    public function testInZoneOutZone()
    {
        $rule = new PricingRule();
        $rule->setExpression('in_zone(pickup.address, "south") and out_zone(dropoff.address, "north") and weight > 5000');
        $rule->setPrice('100');

        $this->assertEquals('Adresse pickup dans zone "south", adresse dropoff hors zone "north", plus de 5.00 kg - €1.00', $this->humanizer->humanize($rule));
    }

    public function testPricePerPackage()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.containsAtLeastOne("Bouquet L")');
        $rule->setPrice('price_per_package(packages, "Bouquet L", 100, 0, 0)');

         $this->assertEquals('Colis Bouquet L - €1.00 par Bouquet L', $this->humanizer->humanize($rule));
    }

    public function testPricePerPackageWithDiscount()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.containsAtLeastOne("Bouquet L")');
        $rule->setPrice('price_per_package(packages, "Bouquet L", 100, 1, 50)');

        $this->assertEquals('Colis Bouquet L - price_per_package(packages, "Bouquet L", 100, 1, 50)', $this->humanizer->humanize($rule));
    }

    public function testPriceRangePerDistance()
    {
        $rule = new PricingRule();
        $rule->setExpression('distance > 5000');
        $rule->setPrice('price_range(distance, 100, 1000, 2000)');

         $this->assertEquals('Plus de 5.00 km - au-dessus de 2.00 km - €1.00 par 1.00 km', $this->humanizer->humanize($rule));
    }

    public function testPriceRangePerWeight()
    {
        $rule = new PricingRule();
        $rule->setExpression('weight > 5000');
        $rule->setPrice('price_range(weight, 100, 1000, 0)');

        $this->assertEquals('Plus de 5.00 kg - €1.00 par 1.00 kg', $this->humanizer->humanize($rule));
    }

    public function testPriceRangePerVolumeUnits()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.totalVolumeUnits() > 5');
        $rule->setPrice('price_range(packages.totalVolumeUnits(), 100, 1, 0)');

        $this->assertEquals('Volume du colis plus de 5 - €1.00 par 1 vu', $this->humanizer->humanize($rule));
    }

    public function testTaskType()
    {
        $rule = new PricingRule();

        $rule->setExpression('task.type == "PICKUP"');
        $rule->setPrice('100');
        $this->assertEquals('Taux de retrait - €1.00', $this->humanizer->humanize($rule));

        $rule->setExpression('task.type == "DROPOFF"');
        $rule->setPrice('100');
        $this->assertEquals('Taux de dépôt - €1.00', $this->humanizer->humanize($rule));
    }

    public function testTimeRangeLength()
    {
        $rule = new PricingRule();
        $rule->setExpression('time_range_length(dropoff, \'hours\', \'< 1.5\')');
        $rule->setPrice('100');

        $this->assertEquals('Créneau horaire de dépôt moins de 1.5 heure - €1.00', $this->humanizer->humanize($rule));
    }

    public function testTimeRangeLengthIn()
    {
        $rule = new PricingRule();
        $rule->setExpression('time_range_length(dropoff, \'hours\', \'in 1..2\')');
        $rule->setPrice('100');

        $this->assertEquals('Créneau horaire de dépôt entre 1 heure et 2 heures  - €1.00', $this->humanizer->humanize($rule));
    }

    public function testDiffHours()
    {
        $rule = new PricingRule();
        $rule->setExpression('diff_hours(pickup, \'< 12\')');
        $rule->setPrice('100');

        $this->assertEquals('Délai de préavis pour retrait moins de 12 heures - €1.00', $this->humanizer->humanize($rule));
    }

    public function testDiffDays()
    {
        $rule = new PricingRule();
        $rule->setExpression('diff_days(pickup, \'> 2\')');
        $rule->setPrice('100');

        $this->assertEquals('Délai de préavis pour retrait plus de 2 jours - €1.00', $this->humanizer->humanize($rule));
    }

    public function testDiffDaysIn()
    {
        $rule = new PricingRule();
        $rule->setExpression('diff_days(pickup, \'in 1..2\')');
        $rule->setPrice('100');

        $this->assertEquals('Délai de préavis pour retrait entre 1 jour et 2 jours - €1.00', $this->humanizer->humanize($rule));
    }

    public function testOrderItemsTotal()
    {
        $rule = new PricingRule();
        $rule->setExpression('order.itemsTotal > 20');
        $rule->setPrice('100');

        $this->assertEquals('Total du panier plus de 20 - €1.00', $this->humanizer->humanize($rule));
    }

    public function testPackagesTotalVolumeUnits()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.totalVolumeUnits() < 5');
        $rule->setPrice('100');

        $this->assertEquals('Volume du colis moins de 5 - €1.00', $this->humanizer->humanize($rule));
    }

    public function testPackagesTotalVolumeUnitsRange()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.totalVolumeUnits() in 1..5');
        $rule->setPrice('100');

        $this->assertEquals('Volume du colis entre 1 vu et 5 vu - €1.00', $this->humanizer->humanize($rule));
    }

    public function testAnd()
    {
        $rule = new PricingRule();
        $rule->setExpression('packages.containsAtLeastOne("📦 - A eurobox/fruitbox of documents") and task.type == "DROPOFF"');
        $rule->setPrice('100');

        $this->assertEquals('Colis 📦 - A eurobox/fruitbox of documents, taux de dépôt - €1.00', $this->humanizer->humanize($rule));
    }

    public function testTimeSlotWithTaskType()
    {
        $rule = new PricingRule();
        $rule->setExpression('time_slot == "/api/time_slots/1" and task.type == "PICKUP"');
        $rule->setPrice('100');

        $this->assertEquals('/api/time_slots/1, taux de retrait - €1.00', $this->humanizer->humanize($rule));
    }

    public function testPricePercentageSurcharge()
    {
        $rule = new PricingRule();

        $rule->setExpression('distance > 5000');
        $rule->setPrice('price_percentage(11000)');
        $this->assertEquals('Plus de 5.00 km - +10%', $this->humanizer->humanize($rule));
    }

    public function testPricePercentageDiscount()
    {
        $rule = new PricingRule();

        $rule->setExpression('distance > 5000');
        $rule->setPrice('price_percentage(9000)');
        $this->assertEquals('Plus de 5.00 km - -10%', $this->humanizer->humanize($rule));
    }
}
