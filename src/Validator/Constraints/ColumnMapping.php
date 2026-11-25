<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * A mapping of a file's columns that an import can actually be run with.
 */
#[\Attribute]
class ColumnMapping extends Constraint
{
    public $missingColumnsMessage = 'import.mapping.error.missing_columns';
    public $duplicateColumnsMessage = 'import.mapping.error.duplicate_columns';

    /**
     * @var string the parser whose columns the mapping is checked against,
     *   as AppBundle\Validator\Constraints\Spreadsheet names them
     */
    public $type = 'delivery';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
