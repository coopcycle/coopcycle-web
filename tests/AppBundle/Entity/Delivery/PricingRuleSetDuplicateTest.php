<?php

namespace Tests\AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery\PricingMatrix;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Pricing\Matrix\MatrixAxis;
use AppBundle\Pricing\Matrix\MatrixAxisEntry;
use AppBundle\Pricing\Matrix\PricingMatrixRuleGenerator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

class PricingRuleSetDuplicateTest extends TestCase
{
    use ProphecyTrait;

    private TranslatorInterface $translator;

    public function setUp(): void
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator
            ->trans('adminDashboard.pricing.copyOf', \Prophecy\Argument::any())
            ->willReturn('Copy of Prices');

        $this->translator = $translator->reveal();
    }

    private function createRuleSetWithMatrix(): PricingRuleSet
    {
        $ruleSet = new PricingRuleSet();
        $ruleSet->setName('Prices');
        $ruleSet->setStrategy('map');

        $matrix = new PricingMatrix();
        $matrix->setName('NO WASTE');
        $matrix->setTarget(PricingRule::TARGET_TASK);
        $matrix->setRowAxis(new MatrixAxis(
            variable: MatrixAxis::VARIABLE_DELIVERY_VOLUME_UNITS,
            entries: [new MatrixAxisEntry(key: 'r_s', label: 'S', min: 1, max: 1)],
        ));
        $matrix->setColumnAxis(new MatrixAxis(
            variable: MatrixAxis::VARIABLE_ZONE,
            entries: [new MatrixAxisEntry(key: 'c_z1', label: 'Z1', value: 'Z1')],
            addressSource: MatrixAxis::ADDRESS_SOURCE_TASK,
        ));
        $matrix->setCells(['r_s:c_z1' => 480]);

        $ruleSet->addMatrix($matrix);
        (new PricingMatrixRuleGenerator())->generate($matrix);

        $bonus = new PricingRule();
        $bonus->setExpression('distance > 5000');
        $bonus->setPrice('100');
        $bonus->setTarget(PricingRule::TARGET_DELIVERY);
        $ruleSet->addRule($bonus);

        return $ruleSet;
    }

    public function testTheGridIsCopiedWithItsCells()
    {
        $copy = $this->createRuleSetWithMatrix()->duplicate($this->translator);

        $this->assertCount(1, $copy->getMatrices());

        $matrixCopy = $copy->getMatrices()->first();

        $this->assertEquals('NO WASTE', $matrixCopy->getName());
        $this->assertEquals(['r_s:c_z1' => 480], $matrixCopy->getCells());
        $this->assertEquals(
            MatrixAxis::VARIABLE_DELIVERY_VOLUME_UNITS,
            $matrixCopy->getRowAxis()->variable
        );
    }

    public function testAGeneratedRulePointsAtTheCopiedGrid()
    {
        $original = $this->createRuleSetWithMatrix();
        $originalMatrix = $original->getMatrices()->first();

        $copy = $original->duplicate($this->translator);
        $matrixCopy = $copy->getMatrices()->first();

        $generated = array_values(array_filter(
            $copy->getRules()->toArray(),
            fn(PricingRule $rule) => $rule->isGenerated()
        ));

        $this->assertCount(1, $generated);
        // Left pointing at the original, saving that grid would reach into this copy
        $this->assertNotSame($originalMatrix, $generated[0]->getMatrix());
        $this->assertSame($matrixCopy, $generated[0]->getMatrix());
        $this->assertEquals('r_s', $generated[0]->getMatrixRowKey());
        $this->assertEquals('c_z1', $generated[0]->getMatrixColumnKey());
        $this->assertCount(1, $matrixCopy->getRules());
    }

    public function testTheCopiedRulesAreNewAndShareNothing()
    {
        $original = $this->createRuleSetWithMatrix();

        $copy = $original->duplicate($this->translator);

        $this->assertCount(2, $copy->getRules());

        foreach ($copy->getRules() as $rule) {
            $this->assertNull($rule->getId());
            $this->assertCount(0, $rule->getProductOptionValues());
            $this->assertSame($copy, $rule->getRuleSet());
        }

        // The original keeps its own rules, and its grid keeps owning only them
        $this->assertCount(2, $original->getRules());
        $this->assertCount(1, $original->getMatrices()->first()->getRules());
    }

    public function testRegeneratingTheCopyLeavesTheOriginalAlone()
    {
        $original = $this->createRuleSetWithMatrix();
        $copy = $original->duplicate($this->translator);

        (new PricingMatrixRuleGenerator())->generate($copy->getMatrices()->first());

        $this->assertCount(2, $copy->getRules());
        $this->assertCount(2, $original->getRules());
    }
}
