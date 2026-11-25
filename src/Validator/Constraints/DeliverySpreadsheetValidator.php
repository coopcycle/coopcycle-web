<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @see SpreadsheetReadabilityTrait, which is all a file of deliveries is checked for:
 *      its columns are matched to the fields they hold on the mapping screen.
 */
class DeliverySpreadsheetValidator extends ConstraintValidator
{
    use SpreadsheetReadabilityTrait;

    public function __construct(private DeliverySpreadsheetParser $parser)
    {}

    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        $this->validateReadability($value, $constraint, $this->parser);
    }
}
