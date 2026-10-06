<?php

namespace AppBundle\Pricing\Matrix;

use AppBundle\Entity\Delivery\PricingMatrix;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;

/**
 * Turns the cells of a pricing matrix into ordinary pricing rules.
 *
 * Rules are matched back to their cell by (row key, column key) and updated in place:
 * a cell that did not change leaves its rule untouched, which matters because
 * PricingRuleSetManager::updateProductOptionValues() disables and recreates the
 * ProductOptionValue of a rule whose price changed, and past orders reference those.
 * Dropping and recreating the rules of a matrix on every save would orphan them.
 *
 * This class does not create ProductOptionValues: the caller does, the same way it does
 * for a hand-written rule (see PricingRuleSetProcessor).
 */
class PricingMatrixRuleGenerator
{
    public function generate(PricingMatrix $matrix): void
    {
        $ruleSet = $matrix->getRuleSet();

        if (null === $ruleSet) {
            throw new \LogicException('A pricing matrix must belong to a rule set before its rules can be generated');
        }

        $rowAxis = $matrix->getRowAxis();
        $columnAxis = $matrix->getColumnAxis();

        /** @var array<string, PricingRule> $existing */
        $existing = [];
        foreach ($matrix->getRules() as $rule) {
            $existing[sprintf('%s:%s', $rule->getMatrixRowKey(), $rule->getMatrixColumnKey())] = $rule;
        }

        $kept = [];

        foreach ($rowAxis->entries as $row) {
            foreach ($columnAxis->entries as $column) {
                $price = $matrix->getCellPrice($row, $column);

                // An empty cell charges nothing, so it generates no rule at all
                if (null === $price) {
                    continue;
                }

                $key = PricingMatrix::cellKey($row, $column);
                $rule = $existing[$key] ?? null;

                if (null === $rule) {
                    $rule = new PricingRule();
                    $rule->setMatrixCell($matrix, $row->key, $column->key);
                    $matrix->addRule($rule);
                    $ruleSet->addRule($rule);
                }

                $rule->setTarget($matrix->getTarget());
                $rule->setExpression($this->expressionFor($matrix, $rowAxis, $row, $columnAxis, $column));
                $rule->setPrice((string) $price);

                $name = $this->nameFor($row, $column);
                if (null !== $name) {
                    $rule->setNameInput($name);
                }

                $kept[$key] = $rule;
            }
        }

        foreach ($existing as $key => $rule) {
            if (!isset($kept[$key])) {
                $matrix->removeRule($rule);
                $ruleSet->removeRule($rule);
            }
        }

        $this->reorder($ruleSet);
    }

    /**
     * Removes every rule a matrix generated, without touching the rest of the rule set.
     */
    public function clear(PricingMatrix $matrix): void
    {
        $ruleSet = $matrix->getRuleSet();

        foreach ($matrix->getRules()->toArray() as $rule) {
            $matrix->removeRule($rule);
            $ruleSet?->removeRule($rule);
        }

        if (null !== $ruleSet) {
            $this->reorder($ruleSet);
        }
    }

    private function expressionFor(
        PricingMatrix $matrix,
        MatrixAxis $rowAxis,
        MatrixAxisEntry $row,
        MatrixAxis $columnAxis,
        MatrixAxisEntry $column
    ): string {
        $conditions = [];

        $taskTypeCondition = $this->taskTypeCondition($matrix, $rowAxis, $columnAxis);
        if (null !== $taskTypeCondition) {
            $conditions[] = $taskTypeCondition;
        }

        $conditions[] = $rowAxis->conditionFor($row);
        $conditions[] = $columnAxis->conditionFor($column);

        return implode(' and ', $conditions);
    }

    /**
     * A per-point matrix restricted to pickups (or to dropoffs) needs to say so, unless
     * one of its axes is a zone read from that point: in the task scope a dropoff gets a
     * 'pickup' object whose address is null, and in_zone() is false on a null address, so
     * in_zone(pickup.address, ...) already filters out dropoffs on its own.
     */
    private function taskTypeCondition(PricingMatrix $matrix, MatrixAxis $rowAxis, MatrixAxis $columnAxis): ?string
    {
        if (PricingRule::TARGET_TASK !== $matrix->getTarget() || null === $matrix->getTaskType()) {
            return null;
        }

        $addressSource = Task::TYPE_PICKUP === $matrix->getTaskType()
            ? MatrixAxis::ADDRESS_SOURCE_PICKUP
            : MatrixAxis::ADDRESS_SOURCE_DROPOFF;

        foreach ([$rowAxis, $columnAxis] as $axis) {
            if (MatrixAxis::VARIABLE_ZONE === $axis->variable && $addressSource === $axis->addressSource) {
                return null;
            }
        }

        return sprintf('task.type == "%s"', $matrix->getTaskType());
    }

    private function nameFor(MatrixAxisEntry $row, MatrixAxisEntry $column): ?string
    {
        if (null === $row->label || null === $column->label) {
            // Let the rule be named after its expression, as a hand-written one would be
            return null;
        }

        return sprintf('%s - %s', $row->label, $column->label);
    }

    /**
     * Matrix rules come first, in grid order, so that a percentage-based bonus placed
     * after them applies to the cell price: within a product variant, option values are
     * applied in rule order, and a percentage multiplies the subtotal of the rules
     * before it.
     */
    private function reorder(PricingRuleSet $ruleSet): void
    {
        $ordered = [];

        foreach ($ruleSet->getMatrices() as $matrix) {
            $rules = [];
            foreach ($matrix->getRules() as $rule) {
                $rules[sprintf('%s:%s', $rule->getMatrixRowKey(), $rule->getMatrixColumnKey())] = $rule;
            }

            foreach ($matrix->getRowAxis()->entries as $row) {
                foreach ($matrix->getColumnAxis()->entries as $column) {
                    $key = PricingMatrix::cellKey($row, $column);
                    if (isset($rules[$key])) {
                        $ordered[] = $rules[$key];
                    }
                }
            }
        }

        foreach ($ruleSet->getRules() as $rule) {
            if (!$rule->isGenerated()) {
                $ordered[] = $rule;
            }
        }

        $position = 0;
        foreach ($ordered as $rule) {
            $rule->setPosition($position++);
        }
    }
}
