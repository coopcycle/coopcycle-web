<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Delivery\PricingMatrix as PricingMatrixEntity;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Task;
use AppBundle\Pricing\Matrix\MatrixAxis;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Checks that a matrix can be turned into rules at all.
 *
 * Gaps and overlaps between entries are deliberately not checked here: overlapping
 * zones cannot be ruled out (two zones may legitimately overlap, and nothing says an
 * address falls in exactly one), so they are reported as a warning in the editor
 * instead of blocking the save.
 */
class PricingMatrixValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (!$value instanceof PricingMatrixEntity) {
            throw new UnexpectedValueException($value, PricingMatrixEntity::class);
        }

        if (!$constraint instanceof PricingMatrix) {
            throw new UnexpectedValueException($constraint, PricingMatrix::class);
        }

        $rowKeys = $this->validateAxis($value->getRowAxisArray(), 'rowAxis', $constraint);
        $columnKeys = $this->validateAxis($value->getColumnAxisArray(), 'columnAxis', $constraint);

        $this->validateTaskType($value, $constraint);

        if (null === $rowKeys || null === $columnKeys) {
            // The cells cannot say anything useful while an axis is broken
            return;
        }

        $this->validateCells($value->getCells(), $rowKeys, $columnKeys, $constraint);
    }

    /**
     * @return string[]|null the entry keys, or null when the axis itself is invalid
     */
    private function validateAxis(array $axis, string $path, PricingMatrix $constraint): ?array
    {
        $variable = $axis['variable'] ?? null;

        if (!in_array($variable, MatrixAxis::variables(), true)) {
            $this->context->buildViolation($constraint->unknownVariableMessage)
                ->setParameter('%variable%', (string) $variable)
                ->atPath($path)
                ->addViolation();

            return null;
        }

        $entries = $axis['entries'] ?? [];

        if (0 === count($entries)) {
            $this->context->buildViolation($constraint->emptyAxisMessage)
                ->atPath($path)
                ->addViolation();

            return null;
        }

        $isNumeric = in_array($variable, MatrixAxis::NUMERIC_VARIABLES, true);

        if (MatrixAxis::VARIABLE_ZONE === $variable
            && !in_array($axis['addressSource'] ?? null, [
                MatrixAxis::ADDRESS_SOURCE_PICKUP,
                MatrixAxis::ADDRESS_SOURCE_DROPOFF,
                MatrixAxis::ADDRESS_SOURCE_TASK,
            ], true)) {
            $this->context->buildViolation($constraint->missingAddressSourceMessage)
                ->atPath($path)
                ->addViolation();

            return null;
        }

        $keys = [];
        $valid = true;

        foreach ($entries as $index => $entry) {
            $key = $entry['key'] ?? null;

            if (null === $key || '' === trim((string) $key)) {
                $this->context->buildViolation($constraint->missingKeyMessage)
                    ->atPath(sprintf('%s.entries[%d].key', $path, $index))
                    ->addViolation();
                $valid = false;
                continue;
            }

            if (in_array($key, $keys, true)) {
                $this->context->buildViolation($constraint->duplicateKeyMessage)
                    ->setParameter('%key%', (string) $key)
                    ->atPath(sprintf('%s.entries[%d].key', $path, $index))
                    ->addViolation();
                $valid = false;
                continue;
            }

            $keys[] = $key;

            if ($isNumeric) {
                $min = $entry['min'] ?? null;
                $max = $entry['max'] ?? null;

                if (null === $min && null === $max) {
                    $this->context->buildViolation($constraint->missingBoundMessage)
                        ->setParameter('%key%', (string) $key)
                        ->atPath(sprintf('%s.entries[%d]', $path, $index))
                        ->addViolation();
                    $valid = false;
                } elseif (null !== $min && null !== $max && (int) $min > (int) $max) {
                    $this->context->buildViolation($constraint->invalidBoundsMessage)
                        ->setParameter('%key%', (string) $key)
                        ->atPath(sprintf('%s.entries[%d]', $path, $index))
                        ->addViolation();
                    $valid = false;
                }
            } else {
                $entryValue = $entry['value'] ?? null;

                if (null === $entryValue || '' === trim((string) $entryValue)) {
                    $this->context->buildViolation($constraint->missingValueMessage)
                        ->setParameter('%key%', (string) $key)
                        ->atPath(sprintf('%s.entries[%d].value', $path, $index))
                        ->addViolation();
                    $valid = false;
                }
            }
        }

        return $valid ? $keys : null;
    }

    private function validateTaskType(PricingMatrixEntity $matrix, PricingMatrix $constraint): void
    {
        $taskType = $matrix->getTaskType();

        if (null === $taskType) {
            return;
        }

        if (!in_array($taskType, [Task::TYPE_PICKUP, Task::TYPE_DROPOFF], true)) {
            $this->context->buildViolation($constraint->unknownTaskTypeMessage)
                ->setParameter('%taskType%', $taskType)
                ->atPath('taskType')
                ->addViolation();

            return;
        }

        if (PricingRule::TARGET_TASK !== $matrix->getTarget()) {
            $this->context->buildViolation($constraint->taskTypeOnOrderMatrixMessage)
                ->atPath('taskType')
                ->addViolation();
        }
    }

    private function validateCells(array $cells, array $rowKeys, array $columnKeys, PricingMatrix $constraint): void
    {
        foreach ($cells as $cell => $price) {
            $parts = explode(':', (string) $cell);

            if (2 !== count($parts)
                || !in_array($parts[0], $rowKeys, true)
                || !in_array($parts[1], $columnKeys, true)) {
                $this->context->buildViolation($constraint->unknownCellMessage)
                    ->setParameter('%cell%', (string) $cell)
                    ->atPath('cells')
                    ->addViolation();
                continue;
            }

            if (!is_int($price) || $price < 0) {
                $this->context->buildViolation($constraint->invalidCellPriceMessage)
                    ->setParameter('%cell%', (string) $cell)
                    ->atPath('cells')
                    ->addViolation();
            }
        }
    }
}
