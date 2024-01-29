<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ProductSpreadsheetValidator extends ConstraintValidator
{
    public function __construct(private DeliverySpreadsheetParser $parser)
    {}

    public function validate($value, Constraint $constraint)
    {
        $spreadsheet = $this->parser->loadSpreadsheet($value);

        $header = $this->parser->getHeaderRow($spreadsheet);

        $expected = [
            'name',
            'price_tax_incl',
            'tax_category',
        ];

        foreach ($expected as $key) {
            if (!in_array($key, $header)) {
                $this->context->buildViolation('spreadsheet.missing_column')
                    ->setParameter('%column%', $key)
                    ->atPath($key)
                    ->addViolation();
            }
        }
    }
}

