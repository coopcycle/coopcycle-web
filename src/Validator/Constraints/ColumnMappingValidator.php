<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Spreadsheet\Column\ColumnMapping as ColumnMappingValue;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
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
        private DeliverySpreadsheetParser $deliveryParser,
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

        $parser = $this->parserFor($constraint->type);

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

    private function parserFor(string $type): DeliverySpreadsheetParser
    {
        return match ($type) {
            'delivery' => $this->deliveryParser,
            default => throw new \InvalidArgumentException(sprintf('No column mapping for "%s"', $type)),
        };
    }
}
