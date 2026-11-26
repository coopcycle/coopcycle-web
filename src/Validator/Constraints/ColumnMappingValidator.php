<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Spreadsheet\Column\ColumnMapping as ColumnMappingValue;
use AppBundle\Import\ImportRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Checks a mapping holds together, wherever it comes from: the mapping screen now, and
 * whatever else may come to set one later.
 */
class ColumnMappingValidator extends ConstraintValidator
{
    public function __construct(
        private ImportRegistry $registry,
        private TranslatorInterface $translator,
    ) {}

    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (!is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        $mapping = ColumnMappingValue::fromArray($value);

        $parser = $this->registry->parserFor($constraint->type);

        // Every row would fail on a column that isn't there, so the file is of no use
        // until it is mapped: the import is never started without one
        $missing = array_diff($parser->getRequiredColumnKeys(), $mapping->mappedKeys());

        if (!empty($missing)) {
            $this->context->buildViolation($constraint->missingColumnsMessage)
                ->setParameter('%columns%', implode(', ', array_map(
                    fn (string $key) => $this->translator->trans(sprintf('import.column.%s', $key)),
                    $missing
                )))
                ->addViolation();
        }

        // A field that can be given in more than one way, an address or its coordinates
        foreach ($parser->getRequiredColumnGroups() as $group) {
            if (!empty(array_intersect($group, $mapping->mappedKeys()))) {
                continue;
            }

            $this->context->buildViolation($constraint->oneOfColumnsMessage)
                ->setParameter('%columns%', implode(', ', array_map(
                    fn (string $key) => $this->translator->trans(sprintf('import.column.%s', $key)),
                    $group
                )))
                ->addViolation();
        }

        // Two columns of the file for one field: there is no telling which one was meant
        $duplicates = $mapping->duplicates();

        if (!empty($duplicates)) {
            $this->context->buildViolation($constraint->duplicateColumnsMessage)
                ->setParameter('%columns%', implode(', ', array_map(
                    fn (string $key) => $this->translator->trans(sprintf('import.column.%s', $key)),
                    $duplicates
                )))
                ->addViolation();
        }
    }
}
