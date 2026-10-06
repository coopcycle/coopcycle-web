<?php

namespace Tests\AppBundle\Pricing\Matrix;

use AppBundle\Entity\Delivery\PricingMatrix;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;
use AppBundle\Pricing\Matrix\MatrixAxis;
use AppBundle\Pricing\Matrix\MatrixAxisEntry;
use AppBundle\Pricing\Matrix\PricingMatrixRuleGenerator;
use PHPUnit\Framework\TestCase;

class PricingMatrixRuleGeneratorTest extends TestCase
{
    private PricingMatrixRuleGenerator $generator;

    public function setUp(): void
    {
        $this->generator = new PricingMatrixRuleGenerator();
    }

    private function createMatrix(
        string $target = PricingRule::TARGET_TASK,
        ?string $taskType = null,
        string $addressSource = MatrixAxis::ADDRESS_SOURCE_TASK
    ): PricingMatrix {
        $ruleSet = new PricingRuleSet();
        $ruleSet->setStrategy('map');

        $matrix = new PricingMatrix();
        $matrix->setTarget($target);
        $matrix->setTaskType($taskType);

        $matrix->setRowAxis(new MatrixAxis(
            variable: MatrixAxis::VARIABLE_DELIVERY_VOLUME_UNITS,
            entries: [
                new MatrixAxisEntry(key: 'r_s', label: 'S', min: 1, max: 1),
                new MatrixAxisEntry(key: 'r_m', label: 'M', min: 2, max: 3),
                new MatrixAxisEntry(key: 'r_xxl', label: 'XXL', min: 10),
            ],
        ));

        $matrix->setColumnAxis(new MatrixAxis(
            variable: MatrixAxis::VARIABLE_ZONE,
            entries: [
                new MatrixAxisEntry(key: 'c_z1', label: 'Z1', value: 'Z1'),
                new MatrixAxisEntry(key: 'c_z2', label: 'Z2', value: 'Z2'),
            ],
            addressSource: $addressSource,
        ));

        $matrix->setCells([
            'r_s:c_z1' => 480,
            'r_s:c_z2' => 660,
            'r_m:c_z1' => 720,
            'r_m:c_z2' => 960,
            'r_xxl:c_z1' => 2160,
            'r_xxl:c_z2' => 2520,
        ]);

        $ruleSet->addMatrix($matrix);

        return $matrix;
    }

    private function expressions(PricingRuleSet $ruleSet): array
    {
        return array_map(
            fn(PricingRule $rule) => $rule->getExpression(),
            $ruleSet->getRules()->toArray()
        );
    }

    public function testGeneratesOneRulePerCell()
    {
        $matrix = $this->createMatrix();

        $this->generator->generate($matrix);

        $ruleSet = $matrix->getRuleSet();

        $this->assertCount(6, $ruleSet->getRules());
        $this->assertEquals([
            'delivery.packages.totalVolumeUnits() in 1..1 and in_zone(task.address, "Z1")',
            'delivery.packages.totalVolumeUnits() in 1..1 and in_zone(task.address, "Z2")',
            'delivery.packages.totalVolumeUnits() in 2..3 and in_zone(task.address, "Z1")',
            'delivery.packages.totalVolumeUnits() in 2..3 and in_zone(task.address, "Z2")',
            'delivery.packages.totalVolumeUnits() > 9 and in_zone(task.address, "Z1")',
            'delivery.packages.totalVolumeUnits() > 9 and in_zone(task.address, "Z2")',
        ], $this->expressions($ruleSet));
    }

    public function testCellPricesAndNames()
    {
        $matrix = $this->createMatrix();

        $this->generator->generate($matrix);

        $rules = $matrix->getRuleSet()->getRules()->toArray();

        $this->assertEquals('480', $rules[0]->getPrice());
        $this->assertEquals('S - Z1', $rules[0]->getNameInput());
        $this->assertEquals('2520', $rules[5]->getPrice());
        $this->assertEquals('XXL - Z2', $rules[5]->getNameInput());

        foreach ($rules as $rule) {
            $this->assertEquals(PricingRule::TARGET_TASK, $rule->getTarget());
            $this->assertTrue($rule->isGenerated());
            $this->assertSame($matrix, $rule->getMatrix());
        }
    }

    public function testEmptyCellGeneratesNoRule()
    {
        $matrix = $this->createMatrix();

        $cells = $matrix->getCells();
        unset($cells['r_m:c_z1']);
        $matrix->setCells($cells);

        $this->generator->generate($matrix);

        $this->assertCount(5, $matrix->getRuleSet()->getRules());
        $this->assertNotContains(
            'delivery.packages.totalVolumeUnits() in 2..3 and in_zone(task.address, "Z1")',
            $this->expressions($matrix->getRuleSet())
        );
    }

    public function testRegeneratingKeepsTheSameRuleObjects()
    {
        $matrix = $this->createMatrix();

        $this->generator->generate($matrix);
        $before = $matrix->getRuleSet()->getRules()->toArray();

        $this->generator->generate($matrix);
        $after = $matrix->getRuleSet()->getRules()->toArray();

        $this->assertCount(6, $after);
        foreach ($before as $index => $rule) {
            $this->assertSame($rule, $after[$index], 'A rule was recreated instead of updated');
        }
    }

    public function testChangingACellUpdatesItsRuleInPlace()
    {
        $matrix = $this->createMatrix();

        $this->generator->generate($matrix);
        $rule = $matrix->getRuleSet()->getRules()->first();

        $matrix->setCells(array_merge($matrix->getCells(), ['r_s:c_z1' => 500]));
        $this->generator->generate($matrix);

        $this->assertSame($rule, $matrix->getRuleSet()->getRules()->first());
        $this->assertEquals('500', $rule->getPrice());
    }

    public function testRemovingARowRemovesOnlyItsRules()
    {
        $matrix = $this->createMatrix();
        $this->generator->generate($matrix);

        $rowAxis = $matrix->getRowAxis();
        $matrix->setRowAxis(new MatrixAxis(
            variable: $rowAxis->variable,
            entries: [$rowAxis->entries[0], $rowAxis->entries[2]],
        ));

        $this->generator->generate($matrix);

        $this->assertCount(4, $matrix->getRuleSet()->getRules());
        $this->assertNotContains(
            'delivery.packages.totalVolumeUnits() in 2..3 and in_zone(task.address, "Z1")',
            $this->expressions($matrix->getRuleSet())
        );
    }

    public function testTaskTypeIsAddedWhenNoZoneAxisImpliesIt()
    {
        $matrix = $this->createMatrix(
            taskType: Task::TYPE_PICKUP,
            addressSource: MatrixAxis::ADDRESS_SOURCE_TASK
        );

        $this->generator->generate($matrix);

        $this->assertEquals(
            'task.type == "PICKUP" and delivery.packages.totalVolumeUnits() in 1..1 and in_zone(task.address, "Z1")',
            $matrix->getRuleSet()->getRules()->first()->getExpression()
        );
    }

    public function testTaskTypeIsOmittedWhenTheZoneAxisAlreadyImpliesIt()
    {
        $matrix = $this->createMatrix(
            taskType: Task::TYPE_PICKUP,
            addressSource: MatrixAxis::ADDRESS_SOURCE_PICKUP
        );

        $this->generator->generate($matrix);

        $this->assertEquals(
            'delivery.packages.totalVolumeUnits() in 1..1 and in_zone(pickup.address, "Z1")',
            $matrix->getRuleSet()->getRules()->first()->getExpression()
        );
    }

    public function testMatrixRulesComeBeforeHandWrittenOnes()
    {
        $matrix = $this->createMatrix();
        $ruleSet = $matrix->getRuleSet();

        $bonus = new PricingRule();
        $bonus->setExpression('diff_days(pickup, "< 1")');
        $bonus->setPrice('price_percentage(12000)');
        $bonus->setTarget(PricingRule::TARGET_DELIVERY);
        $bonus->setPosition(0);
        $ruleSet->addRule($bonus);

        $this->generator->generate($matrix);

        $positions = [];
        foreach ($ruleSet->getRules() as $rule) {
            $positions[$rule->getPosition()] = $rule->isGenerated() ? 'matrix' : 'bonus';
        }
        ksort($positions);

        $this->assertEquals(
            ['matrix', 'matrix', 'matrix', 'matrix', 'matrix', 'matrix', 'bonus'],
            array_values($positions)
        );
    }

    public function testReorderLeavesAMatrixLessRuleSetAlone()
    {
        $ruleSet = new PricingRuleSet();

        $first = new PricingRule();
        $first->setExpression('distance > 1000');
        $first->setPosition(7);
        $ruleSet->addRule($first);

        $second = new PricingRule();
        $second->setExpression('distance > 2000');
        $second->setPosition(3);
        $ruleSet->addRule($second);

        $this->generator->reorder($ruleSet);

        $this->assertEquals(7, $first->getPosition());
        $this->assertEquals(3, $second->getPosition());
    }

    public function testHandWrittenRulesKeepTheirRelativeOrderAfterTheMatrix()
    {
        $matrix = $this->createMatrix();
        $ruleSet = $matrix->getRuleSet();

        $later = new PricingRule();
        $later->setExpression('distance > 2000');
        $later->setPosition(9);
        $ruleSet->addRule($later);

        $sooner = new PricingRule();
        $sooner->setExpression('distance > 1000');
        $sooner->setPosition(2);
        $ruleSet->addRule($sooner);

        $this->generator->generate($matrix);

        // 6 cells first, then the hand-written rules in the order their positions implied
        $this->assertEquals(6, $sooner->getPosition());
        $this->assertEquals(7, $later->getPosition());
    }

    public function testClearRemovesOnlyTheMatrixRules()
    {
        $matrix = $this->createMatrix();
        $ruleSet = $matrix->getRuleSet();

        $bonus = new PricingRule();
        $bonus->setExpression('distance > 5000');
        $bonus->setPrice('100');
        $ruleSet->addRule($bonus);

        $this->generator->generate($matrix);
        $this->assertCount(7, $ruleSet->getRules());

        $this->generator->clear($matrix);

        $this->assertCount(1, $ruleSet->getRules());
        $this->assertSame($bonus, $ruleSet->getRules()->first());
    }
}
