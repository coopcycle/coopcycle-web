<?php

namespace Tests\AppBundle\Pricing;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Entity\Zone;
use AppBundle\ExpressionLanguage\DeliveryExpressionLanguageVisitor;
use AppBundle\ExpressionLanguage\PickupExpressionLanguageProvider;
use AppBundle\ExpressionLanguage\PricePercentageExpressionLanguageProvider;
use AppBundle\ExpressionLanguage\PricePerPackageExpressionLanguageProvider;
use AppBundle\ExpressionLanguage\PriceRangeExpressionLanguageProvider;
use AppBundle\ExpressionLanguage\TaskExpressionLanguageVisitor;
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

    private $priceCalculationVisitor;

    private function createWithExpressionLanguage($expressionLanguage): PriceCalculationVisitor
    {
        return new PriceCalculationVisitor(
            $expressionLanguage,
            new DeliveryExpressionLanguageVisitor(),
            new TaskExpressionLanguageVisitor(
                new DeliveryExpressionLanguageVisitor(),
                self::getContainer()->get(IriConverterInterface::class)
            )
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $expressionLanguage = static::$kernel->getContainer()->get('coopcycle.expression_language');
        $expressionLanguage->registerProvider(
            new PickupExpressionLanguageProvider()
        );
        $expressionLanguage->registerProvider(
            new PricePerPackageExpressionLanguageProvider()
        );
        $expressionLanguage->registerProvider(
            new PriceRangeExpressionLanguageProvider()
        );
        $expressionLanguage->registerProvider(
            new PricePercentageExpressionLanguageProvider()
        );

        $this->priceCalculationVisitor = $this->createWithExpressionLanguage($expressionLanguage);
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
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 3000..5000');
        $rule2->setPrice(699);
        $rule2->setPosition(1);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 5000..7500');
        $rule3->setPrice(899);
        $rule3->setPosition(2);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(599, $output->getPrice());
    }

    public function testGetPriceWithMapStrategy()
    {
        // default: Target "DELIVERY"

        $rule1 = new PricingRule();
        $rule1->setExpression('true');
        $rule1->setPrice(599);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 0..3000');
        $rule2->setPrice(100);
        $rule2->setPosition(1);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 3000..5000');
        $rule3->setPrice(200);
        $rule3->setPosition(2);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(699, $output->getPrice());
    }

    public function testLegacyGetPriceWithMapStrategy()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('true');
        $rule1->setPrice(599);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 0..3000');
        $rule2->setPrice(100);
        $rule2->setPosition(1);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 3000..5000');
        $rule3->setPrice(200);
        $rule3->setPosition(2);

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

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(699, $output->getPrice());
    }

    public function testMultiPointGetPriceWithMapStrategyAndMixedTargets()
    {
        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "< 3")');
        $rule1->setPrice(100);
        $rule1->setTarget(PricingRule::TARGET_DELIVERY);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);
        $rule2->setTarget(PricingRule::TARGET_TASK);

        $rule2->setPosition(1);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));


        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setBefore(new \DateTime('2024-06-17 13:30:00'));
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setBefore(new \DateTime('2024-06-17 13:30:00'));
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(500, $output->getPrice());
    }

    public function testMultiPointGetPriceWithMapStrategyAndTaskTarget()
    {
        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        //Testing: deprecated usage of diff_hours rules on TARGET_TASK; should be applied on TARGET_DELIVERY
        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "< 3")');
        $rule1->setPrice(100);
        $rule1->setTarget(PricingRule::TARGET_TASK);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);
        $rule2->setTarget(PricingRule::TARGET_TASK);

        $rule2->setPosition(1);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));


        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setBefore(new \DateTime('2024-06-17 13:30:00'));
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setBefore(new \DateTime('2024-06-17 13:30:00'));
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(500, $output->getPrice());
    }

    public function testMultiPointGetPriceWithMapStrategyAndDeliveryTarget()
    {
        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "< 3")');
        $rule1->setPrice(100);
        $rule1->setTarget(PricingRule::TARGET_DELIVERY);
        $rule1->setPosition(0);

        //Testing: that 'weight' rule is applied once on entire order (TARGET_DELIVERY) and NOT on each task (TARGET_TASK)
        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);
        $rule2->setTarget(PricingRule::TARGET_DELIVERY);
        $rule2->setPosition(1);


        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));


        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setBefore(new \DateTime('2024-06-17 13:30:00'));
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setBefore(new \DateTime('2024-06-17 13:30:00'));
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(300, $output->getPrice());
    }

    public function testMultiPointGetPriceWithZones()
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
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('in_zone(dropoff.address, "Zone B")');
        $rule2->setPrice(200);
        $rule2->setPosition(1);

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

        $priceCalculationVisitor = $this->createWithExpressionLanguage($expressionLanguage);

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

        $output = $priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(500, $output->getPrice());
    }

    public function testLegacyMultiPointGetPriceWithZones()
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
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('in_zone(dropoff.address, "Zone B")');
        $rule2->setPrice(200);
        $rule2->setPosition(1);

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

        $priceCalculationVisitor = $this->createWithExpressionLanguage($expressionLanguage);

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

        $output = $priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(500, $output->getPrice());
    }

    public function testMultiPointGetPriceWithDiffHoursGreaterThan()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "> 3")');
        $rule1->setPrice(100);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);
        $rule2->setPosition(1);

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


        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(400, $output->getPrice());
    }

    public function testLegacyMultiPointGetPriceWithDiffHoursGreaterThan()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "> 3")');
        $rule1->setPrice(100);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);
        $rule2->setPosition(1);

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


        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(400, $output->getPrice());
    }

    public function testMultiPointGetPriceWithDiffHoursLessThan()
    {
        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "< 3")');
        $rule1->setPrice(100);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);
        $rule2->setPosition(1);

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

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(100, $output->getPrice());
    }

    public function testLegacyMultiPointGetPriceWithDiffHoursLessThan()
    {
        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('diff_hours(pickup, "< 3")');
        $rule1->setPrice(100);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('weight > 5000');
        $rule2->setPrice(200);
        $rule2->setPosition(1);

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

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(100, $output->getPrice());
    }

    public function testGetPriceWithDiffDaysGreaterThan()
    {
        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('diff_days(pickup, "> 3")');
        $rule1->setPrice(100);
        $rule1->setTarget(PricingRule::TARGET_DELIVERY);
        $rule1->setPosition(0);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));


        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setBefore(new \DateTime('2024-06-25 13:30:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(100, $output->getPrice());
    }

    public function testGetPriceWithDiffDaysLessThan()
    {
        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('diff_days(pickup, "< 3")');
        $rule1->setPrice(100);
        $rule1->setTarget(PricingRule::TARGET_DELIVERY);
        $rule1->setPosition(0);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));


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

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(100, $output->getPrice());
    }

    public function testMultiPointGetPriceWithTaskTypeCondition()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('task.type == "PICKUP"');
        $rule1->setPrice(100);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('task.type == "DROPOFF"');
        $rule2->setPrice(200);
        $rule2->setPosition(1);

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


        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(500, $output->getPrice());
    }

    public function testLegacyMultiPointGetPriceWithTaskTypeCondition()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('task.type == "PICKUP"');
        $rule1->setPrice(100);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('task.type == "DROPOFF"');
        $rule2->setPrice(200);
        $rule2->setPosition(1);

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


        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(500, $output->getPrice());
    }

    public function testApplyWeightRuleOnSumWithDeliveryTarget()
    {
        //Testing: that 'weight' rule is applied on sum of weights of all tasks
        $rule1 = new PricingRule();
        $rule1->setExpression('weight > 11000');
        $rule1->setPrice(200);
        $rule1->setTarget(PricingRule::TARGET_DELIVERY);
        $rule1->setPosition(0);


        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(200, $output->getPrice());
    }

    public function testApplyWeightRuleOnEachTaskWithTaskTarget()
    {
        //Testing: that 'weight' rule is applied on sum of weights of all tasks
        $rule1 = new PricingRule();
        $rule1->setExpression('weight > 5000');
        $rule1->setPrice(200);
        $rule1->setTarget(PricingRule::TARGET_TASK);
        $rule1->setPosition(0);


        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(400, $output->getPrice());
    }

    public function testGetPriceWithTimeRangeLengthLessThan()
    {
        // default: Strategy "find"
        // default: Target "DELIVERY"

        $rule1 = new PricingRule();
        $rule1->setExpression('time_range_length(pickup, "hours", "< 1")');
        $rule1->setPrice(599);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('time_range_length(pickup, "hours", "> 1")');
        $rule2->setPrice(301);
        $rule2->setPosition(1);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));


        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setAfter(new \DateTime('2024-06-17 13:00:00'));
        $pickup->setBefore(new \DateTime('2024-06-17 13:59:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(599, $output->getPrice());
    }

    public function testGetPriceWithTimeRangeLengthGreaterThan()
    {
        // default: Strategy "find"
        // default: Target "DELIVERY"

        $rule1 = new PricingRule();
        $rule1->setExpression('time_range_length(pickup, "hours", "< 1")');
        $rule1->setPrice(599);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('time_range_length(pickup, "hours", "> 1")');
        $rule2->setPrice(301);
        $rule2->setPosition(1);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setAfter(new \DateTime('2024-06-17 13:00:00'));
        $pickup->setBefore(new \DateTime('2024-06-17 15:30:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(301, $output->getPrice());
    }

    public function testApplyPackagesRuleOnSumWithDeliveryTarget()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('packages.containsAtLeastOne("XXL")');
        $rule1->setPrice(100);
        $rule1->setTarget(PricingRule::TARGET_DELIVERY);
        $rule1->setPosition(0);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));


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

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(100, $output->getPrice());
    }

    public function testApplyPackagesRuleOnEachTaskWithTaskTarget()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('packages.containsAtLeastOne("XXL")');
        $rule1->setPrice(100);
        $rule1->setTarget(PricingRule::TARGET_TASK);
        $rule1->setPosition(0);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));


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

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(200, $output->getPrice());
    }

    public function testApplyPackagesTotalVolumeUnitsOnSumWithDeliveryTarget()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('packages.totalVolumeUnits() > 29');
        $rule1->setPrice(100);
        $rule1->setTarget(PricingRule::TARGET_DELIVERY);
        $rule1->setPosition(0);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));


        $package = new Package();
        $package->setName('XXL');
        $package->setMaxVolumeUnits(10);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->addPackageWithQuantity($package, 1);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->addPackageWithQuantity($package, 2);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(100, $output->getPrice());
    }

    public function testApplyPackagesTotalVolumeUnitsOnEachTaskWithTaskTarget()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('packages.totalVolumeUnits() > 9');
        $rule1->setPrice(100);
        $rule1->setTarget(PricingRule::TARGET_TASK);
        $rule1->setPosition(0);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));


        $package = new Package();
        $package->setName('XXL');
        $package->setMaxVolumeUnits(10);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->addPackageWithQuantity($package, 1);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->addPackageWithQuantity($package, 2);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(200, $output->getPrice());
    }

    public function testMultiPointGetPriceWithPricePerPackage()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('task.type == "PICKUP"');
        $rule1->setPrice(100);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('task.type == "DROPOFF"');
        $rule2->setPrice('price_per_package(packages, "XXL", 100, 0, 0)');
        $rule2->setPosition(1);

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

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(400, $output->getPrice());
    }

    public function testLegacyMultiPointGetPriceWithPricePerPackage()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('task.type == "PICKUP"');
        $rule1->setPrice(100);
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('task.type == "DROPOFF"');
        $rule2->setPrice('price_per_package(packages, "XXL", 100, 0, 0)');
        $rule2->setPosition(1);

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

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(400, $output->getPrice());
    }

    public function testGetPriceWithPriceRangeByDistance()
    {
        // default: Strategy "find"
        // default: Target "DELIVERY"

        $rule1 = new PricingRule();
        $rule1->setExpression('distance > 0');
        // 2 EUR per 1 km above 3 km
        $rule1->setPrice('price_range(distance, 200, 1000, 3000)');
        $rule1->setPosition(0);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));

        $delivery = new Delivery();
        $delivery->setDistance(6000);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(600, $output->getPrice());
    }

    public function testGetPriceWithPriceRangeByWeight()
    {
        // default: Strategy "find"
        // default: Target "DELIVERY"

        $rule1 = new PricingRule();
        $rule1->setExpression('weight > 0');
        // 2 EUR per 1 kg above 0 kg
        $rule1->setPrice('price_range(weight, 200, 1000, 0)');
        $rule1->setPosition(0);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(5500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(2400, $output->getPrice());
    }

    public function testGetPriceWithPriceRangeByVolumeUnits()
    {
        // default: Strategy "find"
        // default: Target "DELIVERY"

        $rule1 = new PricingRule();
        $rule1->setExpression('distance > 0'); // a hack to match any order/delivery
        // 2 EUR per 1 volume unit above 0
        $rule1->setPrice('price_range(packages.totalVolumeUnits(), 200, 1, 0)');
        $rule1->setPosition(0);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
        ]));

        $package = new Package();
        $package->setName('XXL');
        $package->setMaxVolumeUnits(10);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->addPackageWithQuantity($package, 1);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->addPackageWithQuantity($package, 2);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);
        $delivery->setDistance(1500);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(6000, $output->getPrice());
    }

    public function testGetPriceWithMapStrategyAndPricePercentageSurcharge()
    {
        // default: Target "DELIVERY"

        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('distance > 0'); // a hack to match any order/delivery
        // base price: 5 EUR
        $rule1->setPrice('500');
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('diff_days(pickup, "< 3")');
        // short notice: 15% surcharge
        $rule2->setPrice('price_percentage(11500)');
        $rule2->setPosition(1);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1]);
        $delivery->setDistance(1500);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(575, $output->getPrice());
    }

    public function testGetPriceWithMapStrategyAndPricePercentageDiscount()
    {
        // default: Target "DELIVERY"

        Carbon::setTestNow(Carbon::parse('2024-06-12 12:00:00'));

        $rule1 = new PricingRule();
        $rule1->setExpression('distance > 0'); // a hack to match any order/delivery
        // base price: 5 EUR
        $rule1->setPrice('500');
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setExpression('diff_days(pickup, "> 3")');
        // advance notice: 15% discount
        $rule2->setPrice('price_percentage(8500)');
        $rule2->setPosition(1);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
        ]));

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setBefore(new \DateTime('2024-06-17 13:30:00'));

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1]);
        $delivery->setDistance(1500);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(425, $output->getPrice());
    }

    /**
     * Tests that the price percentage is applied separately on each task
     */
    public function testGetPriceWithMapStrategyAndPricePercentageOnEachTask()
    {
        $rule1 = new PricingRule();
        $rule1->setTarget(PricingRule::TARGET_TASK);
        $rule1->setExpression('task.type == "DROPOFF"'); // a hack to match any dropoff task
        $rule1->setPrice('500');
        $rule1->setPosition(0);

        $rule2 = new PricingRule();
        $rule2->setTarget(PricingRule::TARGET_TASK);
        $rule2->setExpression('weight > 5000 and weight < 10000');
        // weight > 5kg: 15% surcharge
        $rule2->setPrice('price_percentage(11500)');
        $rule2->setPosition(1);

        $rule3 = new PricingRule();
        $rule3->setTarget(PricingRule::TARGET_TASK);
        $rule3->setExpression('weight > 10000');
        // weight > 10kg: 50% surcharge
        $rule3->setPrice('price_percentage(15000)');
        $rule3->setPosition(2);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);
        $dropoff1->setWeight(6000);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $dropoff2->setWeight(12500);

        $delivery = Delivery::createWithTasks(...[$pickup, $dropoff1, $dropoff2]);
        $delivery->setDistance(1500);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(1325, $output->getPrice());
    }

    /**
     * Tests that the price percentage is applied on the entire order (sum of all tasks and all previous delivery-based rules)
     */
    public function testGetPriceWithMapStrategyAndPricePercentageOnEntireOrder()
    {

        Carbon::setTestNow(Carbon::parse('2024-06-17 12:00:00'));

        // price per dropoff point
        $rule1 = new PricingRule();
        $rule1->setTarget(PricingRule::TARGET_TASK);
        $rule1->setExpression('task.type == "DROPOFF"'); // a hack to match any dropoff task
        $rule1->setPrice('500');
        $rule1->setPosition(0);

        // base price per order
        $rule2 = new PricingRule();
        $rule2->setExpression('distance > 0'); // a hack to match any order/delivery
        // base price: 3 EUR
        $rule2->setPrice('300');
        $rule2->setPosition(1);

        $rule3 = new PricingRule();
        $rule3->setExpression('diff_days(pickup, "< 3")');
        // short notice: 15% surcharge
        $rule3->setPrice('price_percentage(11500)');
        $rule3->setPosition(2);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

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
        $delivery->setDistance(1500);

        $output = $this->priceCalculationVisitor->visit($delivery, $ruleSet);
        $this->assertEquals(1495, $output->getPrice());
    }
}
