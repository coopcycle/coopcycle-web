<?php

namespace AppBundle\Pricing\Matrix;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Rules generated from a pricing matrix are owned by that matrix and cannot be edited
 * on their own: they are rewritten whenever the matrix is saved, so an edit made here
 * would silently disappear.
 *
 * The rule set endpoint accepts a whole rules array, so the check belongs on the server
 * and not only in the form. What a client cannot do at all is turn a rule into a
 * generated one: the matrix fields are not in the 'pricing_rule_set:write' group.
 */
class GeneratedRuleGuard
{
    /**
     * Fields that belong to the matrix. 'position' is deliberately absent: it is
     * bookkeeping rather than user intent, and PricingMatrixRuleGenerator::reorder()
     * puts it back in order on every save.
     */
    private const OWNED_FIELDS = ['expression', 'price', 'target'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return ConstraintViolationList the edits that must be refused, empty when there are none
     */
    public function findViolations(PricingRuleSet $ruleSet): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();

        $uow = $this->entityManager->getUnitOfWork();
        $uow->computeChangeSets();

        foreach ($ruleSet->getRules() as $rule) {
            if (!$rule->isGenerated() || null === $rule->getId()) {
                continue;
            }

            $changed = array_intersect(
                array_keys($uow->getEntityChangeSet($rule)),
                self::OWNED_FIELDS
            );

            foreach ($changed as $field) {
                $violations->add($this->violation($rule, $field, sprintf(
                    'The %s of this rule is set by the pricing matrix "%s" and cannot be edited directly.',
                    $field,
                    $this->matrixName($rule)
                )));
            }

            $name = $rule->getNameInput();
            if (null !== $name && trim($name) !== (string) $rule->getName()) {
                $violations->add($this->violation($rule, 'name', sprintf(
                    'The name of this rule is set by the pricing matrix "%s" and cannot be edited directly.',
                    $this->matrixName($rule)
                )));
            }
        }

        // A generated rule left out of the payload would be orphaned, and so deleted
        foreach ($ruleSet->getMatrices() as $matrix) {
            foreach ($matrix->getRules() as $rule) {
                if (null === $rule->getId() || $ruleSet->getRules()->contains($rule)) {
                    continue;
                }

                $violations->add($this->violation($rule, 'rules', sprintf(
                    'This rule belongs to the pricing matrix "%s" and cannot be removed on its own. Edit the matrix instead.',
                    $matrix->getName() ?? (string) $matrix->getId()
                )));
            }
        }

        return $violations;
    }

    private function matrixName(PricingRule $rule): string
    {
        $matrix = $rule->getMatrix();

        return $matrix?->getName() ?? (string) $matrix?->getId();
    }

    private function violation(PricingRule $rule, string $field, string $message): ConstraintViolation
    {
        return new ConstraintViolation(
            $message,
            $message,
            [],
            $rule,
            sprintf('rules[%s].%s', $rule->getId(), $field),
            null
        );
    }
}
