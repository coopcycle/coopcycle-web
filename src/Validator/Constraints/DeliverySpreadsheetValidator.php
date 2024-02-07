<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DeliverySpreadsheetValidator extends ConstraintValidator
{
    public function __construct(private DeliverySpreadsheetParser $parser)
    {}

    public function validate($value, Constraint $constraint)
    {
        $spreadsheet = $this->parser->loadSpreadsheet($value);

        $header = $this->parser->getHeaderRow($spreadsheet);

        $hasPickupAddress = in_array('pickup.address', $header);
        $hasDropoffAddress = in_array('dropoff.address', $header);

        if (!$hasPickupAddress) {
            $this->context->buildViolation($constraint->missingColumnMessage)
                ->setParameter('%column%', 'pickup.address')
                ->atPath('pickup.address')
                ->addViolation();
        }

        if (!$hasDropoffAddress) {
            $this->context->buildViolation($constraint->missingColumnMessage)
                ->setParameter('%column%', 'dropoff.address')
                ->atPath('dropoff.address')
                ->addViolation();
        }
    }
}
