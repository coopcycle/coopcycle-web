<?php

namespace Tests\AppBundle\Pricing\Matrix;

use AppBundle\Entity\Delivery\PricingMatrix;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Fixtures\DatabasePurger;
use AppBundle\Pricing\Matrix\GeneratedRuleGuard;
use AppBundle\Pricing\Matrix\MatrixAxis;
use AppBundle\Pricing\Matrix\MatrixAxisEntry;
use AppBundle\Pricing\Matrix\PricingMatrixRuleGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs against a real UnitOfWork: what the guard refuses depends on the change sets
 * Doctrine actually computes, which is the part worth testing.
 */
class GeneratedRuleGuardTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private GeneratedRuleGuard $guard;
    private PricingRuleSet $ruleSet;
    private PricingMatrix $matrix;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->guard = new GeneratedRuleGuard($this->entityManager);

        $dbPurger = self::getContainer()->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();

        $this->ruleSet = new PricingRuleSet();
        $this->ruleSet->setName('Matrix rule set');
        $this->ruleSet->setStrategy('map');

        $this->matrix = new PricingMatrix();
        $this->matrix->setName('NO WASTE');
        $this->matrix->setTarget(PricingRule::TARGET_TASK);
        $this->matrix->setRowAxis(new MatrixAxis(
            variable: MatrixAxis::VARIABLE_DELIVERY_VOLUME_UNITS,
            entries: [
                new MatrixAxisEntry(key: 'r_s', label: 'S', min: 1, max: 1),
                new MatrixAxisEntry(key: 'r_m', label: 'M', min: 2, max: 3),
            ],
        ));
        $this->matrix->setColumnAxis(new MatrixAxis(
            variable: MatrixAxis::VARIABLE_ZONE,
            entries: [new MatrixAxisEntry(key: 'c_z1', label: 'Z1', value: 'Z1')],
            addressSource: MatrixAxis::ADDRESS_SOURCE_TASK,
        ));
        $this->matrix->setCells([
            'r_s:c_z1' => 480,
            'r_m:c_z1' => 720,
        ]);

        $this->ruleSet->addMatrix($this->matrix);

        (new PricingMatrixRuleGenerator())->generate($this->matrix);

        $this->entityManager->persist($this->ruleSet);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->ruleSet = $this->entityManager
            ->getRepository(PricingRuleSet::class)
            ->find($this->ruleSet->getId());
        $this->matrix = $this->ruleSet->getMatrices()->first();
    }

    private function generatedRule(): PricingRule
    {
        foreach ($this->ruleSet->getRules() as $rule) {
            if ($rule->isGenerated()) {
                return $rule;
            }
        }

        $this->fail('No generated rule in the rule set');
    }

    public function testTheMatrixGeneratedItsRules()
    {
        $this->assertCount(2, $this->ruleSet->getRules());
        $this->assertCount(2, $this->matrix->getRules());
        $this->assertTrue($this->generatedRule()->isGenerated());
    }

    public function testUntouchedGeneratedRulesAreAccepted()
    {
        $this->assertCount(0, $this->guard->findViolations($this->ruleSet));
    }

    public function testEditingTheExpressionOfAGeneratedRuleIsRefused()
    {
        $this->generatedRule()->setExpression('distance > 1000');

        $violations = $this->guard->findViolations($this->ruleSet);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('NO WASTE', $violations->get(0)->getMessage());
        $this->assertStringContainsString('expression', $violations->get(0)->getMessage());
    }

    public function testEditingThePriceOfAGeneratedRuleIsRefused()
    {
        $this->generatedRule()->setPrice('9999');

        $violations = $this->guard->findViolations($this->ruleSet);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('price', $violations->get(0)->getMessage());
    }

    public function testEditingTheTargetOfAGeneratedRuleIsRefused()
    {
        $this->generatedRule()->setTarget(PricingRule::TARGET_DELIVERY);

        $this->assertCount(1, $this->guard->findViolations($this->ruleSet));
    }

    public function testRenamingAGeneratedRuleIsRefused()
    {
        $this->generatedRule()->setNameInput('Something else');

        $violations = $this->guard->findViolations($this->ruleSet);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('name', $violations->get(0)->getMessage());
    }

    public function testMovingAGeneratedRuleIsAccepted()
    {
        // Position is put back in order on every save, so it is not worth refusing
        $this->generatedRule()->setPosition(42);

        $this->assertCount(0, $this->guard->findViolations($this->ruleSet));
    }

    public function testRemovingAGeneratedRuleFromThePayloadIsRefused()
    {
        $this->ruleSet->removeRule($this->generatedRule());

        $violations = $this->guard->findViolations($this->ruleSet);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('cannot be removed', $violations->get(0)->getMessage());
    }

    public function testEditingAHandWrittenRuleIsAccepted()
    {
        $bonus = new PricingRule();
        $bonus->setExpression('distance > 5000');
        $bonus->setPrice('100');
        $bonus->setTarget(PricingRule::TARGET_DELIVERY);
        $bonus->setPosition(10);
        $this->ruleSet->addRule($bonus);

        $this->entityManager->flush();

        $bonus->setExpression('distance > 6000');
        $bonus->setPrice('200');

        $this->assertCount(0, $this->guard->findViolations($this->ruleSet));
    }
}
