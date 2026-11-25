<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks an uploaded file can be read at all.
 *
 * Not what its columns are called: which field each of them holds is asked on the mapping
 * screen, and a file naming its columns anything it likes is exactly what that is for.
 * Whether the fields a delivery can't do without are all covered is settled there, and by
 * the parser when the file is read.
 */
class DeliverySpreadsheetValidator extends ConstraintValidator
{
    public function __construct(private DeliverySpreadsheetParser $parser)
    {}

    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        try {
            // A handful of rows: the file is read in full by a worker, not on this request
            $preview = $this->parser->preview($value);
        } catch (\Throwable $e) {
            // Among others the "Unsupported file type" of a file that is not a spreadsheet,
            // which would otherwise come out of the form as a 500
            $this->context->buildViolation($constraint->unreadableMessage)
                ->addViolation();

            return;
        }

        // Without a header row there is nothing to show on the mapping screen
        if ($preview->isEmpty()) {
            $this->context->buildViolation($constraint->missingHeaderMessage)
                ->addViolation();

            return;
        }

        if (!$preview->hasRows()) {
            $this->context->buildViolation($constraint->emptyMessage)
                ->addViolation();
        }
    }
}
