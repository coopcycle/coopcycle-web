<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Spreadsheet extends Constraint
{
    public $type;

    public $missingColumnMessage = 'spreadsheet.missing_column';
    public $missingColumnsMessage = 'spreadsheet.missing_columns';
    public $alternativeColumnsMessage = 'spreadsheet.alternative_columns';
    public $csvEncodingMessage = 'spreadsheet.csv_encoding';

    public function __construct(string $type = null, array $options = null, array $groups = null, $payload = null)
    {
        parent::__construct($options ?? [], $groups, $payload);

        $this->type = $type;
    }

    public function validatedBy()
    {
        $class = new \ReflectionClass($this);

        return $class->getNamespaceName().'\\'.ucfirst($this->type).$class->getShortName().'Validator';
    }
}

