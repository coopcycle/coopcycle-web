<?php

namespace Tests\AppBundle\Pricing;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Entity\Zone;
use AppBundle\ExpressionLanguage\PickupExpressionLanguageProvider;
use AppBundle\ExpressionLanguage\PricePerPackageExpressionLanguageProvider;
use AppBundle\ExpressionLanguage\ZoneExpressionLanguageProvider;
use AppBundle\Pricing\PriceCalculationVisitor;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class PriceCalculationVisitorTest extends KernelTestCase
{
    use ProphecyTrait;

    private $expressionLanguage;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->expressionLanguage = static::$kernel->getContainer()->get('coopcycle.expression_language');
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testGetPrice()
    {
        // default: Strategy "find"
        // default: Target "DELIVERY"

        $rule1 = new PricingRule();
        $rule1->setExpression('distance in 0..3000');
        $rule1->setPrice(599);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 3000..5000');
        $rule2->setPrice(699);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 5000..7500');
        $rule3->setPrice(899);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $visitor = new PriceCalculationVisitor($ruleSet, $this->expressionLanguage);

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $visitor->visit($delivery);
        $this->assertEquals(599, $visitor->getPrice());
    }

    public function testGetPriceWithMapStrategy()
    {
        // default: Target "DELIVERY"

        $rule1 = new PricingRule();
        $rule1->setExpression('true');
        $rule1->setPrice(599);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 0..3000');
        $rule2->setPrice(100);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 3000..5000');
        $rule3->setPrice(200);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $visitor = new PriceCalculationVisitor($ruleSet, $this->expressionLanguage);

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $visitor->visit($delivery);
        $this->assertEquals(699, $visitor->getPrice());
    }

    public function testLegacyGetPriceWithMapStrategy()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('true');
        $rule1->setPrice(599);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 0..3000');
        $rule2->setPrice(100);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 3000..5000');
        $rule3->setPrice(200);

        foreach ([
                     $rule1,
                     $rule2,
                     $rule3,
                 ] as $rule) {
            $rule->setTarget(PricingRule::LEGACY_TARGET_DYNAMIC);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $visitor = new PriceCalculationVisitor($ruleSet, $this->expressionLanguage);

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $visitor->visit($delivery);
        $this->assertEquals(699, $visitor->getPrice());
    }

    public function testGetMultiPriceWithZones()
    {
        $pickupAddress = new Address();
        $pickupAddress->setStreetAddress('Pickup 1');

        $dropoff1Address = new Address();
        $dropoff1Address->setStreetAddress('Dropoff 1');

        $dropoff2Address = new Address();
        $dropoff2Address->setStreetAddress('Dropoff 2');

        $rule1 = new PricingRule();
        $rule1->setExpression('in_zone(pickup.address, "Zone A")');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('in_zone(dropoff.address, "Zone B")');
        $rule2->setPrice(200);

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::TARGET_TASK);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $zoneA = $this->prophesize(Zone::class);
        $zoneA
            ->containsAddress(Argument::type(Address::class))
            ->will(function ($args) use ($pickupAddress) {

                if ($args[0] === $pickupAddress) {

                    return true;
                }

                return false;
            });

        $zoneB = $this->prophesize(Zone::class);
        $zoneB
            ->containsAddress(Argument::type(Address::class))
            ->will(function ($args) use ($dropoff1Address, $dropoff2Address) {

                if ($args[0] === $dropoff1Address || $args[0] === $dropoff2Address) {

                    return true;
                }

                return false;
            });

        $zoneRepository = $this->prophesize(EntityRepository::class);
        $zoneRepository
            ->findOneBy(['name' => 'Zone A'])
            ->willReturn($zoneA->reveal());
        $zoneRepository
            ->findOneBy(['name' => 'Zone B'])
            ->willReturn($zoneB->reveal());

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(
            new ZoneExpressionLanguageProvider($zoneRepository->reveal())
        );

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setAddress($pickupAddress);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setAddress($dropoff1Address);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setAddress($dropoff2Address);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(500, $visitor->getPrice());
    }

    public function testLegacyGetMultiPriceWithZones()
    {
        $pickupAddress = new Address();
        $pickupAddress->setStreetAddress('Pickup 1');

        $dropoff1Address = new Address();
        $dropoff1Address->setStreetAddress('Dropoff 1');

        $dropoff2Address = new Address();
        $dropoff2Address->setStreetAddress('Dropoff 2');

        $rule1 = new PricingRule();
        $rule1->setExpression('in_zone(pickup.address, "Zone A")');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('in_zone(dropoff.address, "Zone B")');
        $rule2->setPrice(200);

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::LEGACY_TARGET_DYNAMIC);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $zoneA = $this->prophesize(Zone::class);
        $zoneA
            ->containsAddress(Argument::type(Address::class))
            ->will(function ($args) use ($pickupAddress) {

                if ($args[0] === $pickupAddress) {

                    return true;
                }

                return false;
            });

        $zoneB = $this->prophesize(Zone::class);
        $zoneB
            ->containsAddress(Argument::type(Address::class))
            ->will(function ($args) use ($dropoff1Address, $dropoff2Address) {

                if ($args[0] === $dropoff1Address || $args[0] === $dropoff2Address) {

                    return true;
                }

                return false;
            });

        $zoneRepository = $this->prophesize(EntityRepository::class);
        $zoneRepository
            ->findOneBy(['name' => 'Zone A'])
            ->willReturn($zoneA->reveal());
        $zoneRepository
            ->findOneBy(['name' => 'Zone B'])
            ->willReturn($zoneB->reveal());

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(
            new ZoneExpressionLanguageProvider($zoneRepository->reveal())
        );

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setAddress($pickupAddress);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setAddress($dropoff1Address);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setAddress($dropoff2Address);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(500, $visitor->getPrice());
    }

    public function testGetMultiPriceWithDiffHoursGreaterThan()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "> 3")');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::TARGET_TASK);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(
            new PickupExpressionLanguageProvider()
        );

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(400, $visitor->getPrice());
    }

    public function testLegacyGetMultiPriceWithDiffHoursGreaterThan()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "> 3")');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::LEGACY_TARGET_DYNAMIC);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(
            new PickupExpressionLanguageProvider()
        );

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(400, $visitor->getPrice());
    }

    public function testGetMultiPriceWithTaskTypeCondition()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('task.type == "PICKUP"');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('task.type == "DROPOFF"');
        $rule2->setPrice(200);

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::TARGET_TASK);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $expressionLanguage = new ExpressionLanguage();

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(500, $visitor->getPrice());
    }

    public function testLegacyGetMultiPriceWithTaskTypeCondition()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('task.type == "PICKUP"');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('task.type == "DROPOFF"');
        $rule2->setPrice(200);

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::LEGACY_TARGET_DYNAMIC);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $expressionLanguage = new ExpressionLanguage();

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(500, $visitor->getPrice());
    }

    public function testGetMultiPriceWithPricePerPackage()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('task.type == "PICKUP"');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('task.type == "DROPOFF"');
        $rule2->setPrice('price_per_package(packages, "XXL", 100, 0, 0)');

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::TARGET_TASK);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(
            new PricePerPackageExpressionLanguageProvider()
        );

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $package = new Package();
        $package->setName('XXL');

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->addPackageWithQuantity($package, 1);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->addPackageWithQuantity($package, 2);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(400, $visitor->getPrice());
    }

    public function testLegacyGetMultiPriceWithPricePerPackage()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('task.type == "PICKUP"');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('task.type == "DROPOFF"');
        $rule2->setPrice('price_per_package(packages, "XXL", 100, 0, 0)');

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::LEGACY_TARGET_DYNAMIC);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(
            new PricePerPackageExpressionLanguageProvider()
        );

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $package = new Package();
        $package->setName('XXL');

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->addPackageWithQuantity($package, 1);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->addPackageWithQuantity($package, 2);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(400, $visitor->getPrice());
    }

    public function testGetMultiPriceWithDiffHoursLessThan()
    {
        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "< 3")');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::TARGET_TASK);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(
            new PickupExpressionLanguageProvider()
        );

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(100, $visitor->getPrice());
    }

    public function testLegacyGetMultiPriceWithDiffHoursLessThan()
    {
        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "< 3")');
        $rule1->setPrice(100);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);

        foreach ([
                     $rule1,
                     $rule2,
                 ] as $rule) {
            $rule->setTarget(PricingRule::LEGACY_TARGET_DYNAMIC);
        }

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(
            new PickupExpressionLanguageProvider()
        );

        $visitor = new PriceCalculationVisitor($ruleSet, $expressionLanguage);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $visitor->visit($delivery);
        $this->assertEquals(100, $visitor->getPrice());
    }
}
