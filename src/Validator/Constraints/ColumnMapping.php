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
    public $oneOfColumnsMessage = 'import.mapping.error.one_of_columns';

    /**
     * @var string the kind of import whose columns the mapping is checked against,
     *   as AppBundle\Entity\ImportQueue::TYPE_* names them
     */
    public $type = 'deliveries';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
