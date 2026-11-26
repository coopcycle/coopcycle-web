<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class Spreadsheet extends Constraint
{
    public $type;

    /**
     * Whether the columns of the file will be matched to the fields they hold before it
     * is read. A file that will be mapped is only checked for being readable: what its
     * columns are called is beside the point.
     */
    public $mapped = false;

    public $missingColumnMessage = 'spreadsheet.missing_column';
    public $missingColumnsMessage = 'spreadsheet.missing_columns';
    public $alternativeColumnsMessage = 'spreadsheet.alternative_columns';
    public $csvEncodingMessage = 'spreadsheet.csv_encoding';
    public $unreadableMessage = 'spreadsheet.unreadable';
    public $missingHeaderMessage = 'spreadsheet.missing_header';
    public $emptyMessage = 'spreadsheet.empty';

    public function __construct(?string $type = null, ?array $options = null, ?array $groups = null, $payload = null)
    {
        parent::__construct($options ?? [], $groups, $payload);

        $this->type = $type;
    }

    public function validatedBy(): string
    {
        $class = new \ReflectionClass($this);

        return $class->getNamespaceName().'\\'.ucfirst($this->type).$class->getShortName().'Validator';
    }
}

