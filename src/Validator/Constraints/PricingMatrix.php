<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PricingMatrix extends Constraint
{
    public $unknownVariableMessage = 'Unknown matrix axis variable "%variable%".';
    public $emptyAxisMessage = 'A matrix axis needs at least one entry.';
    public $duplicateKeyMessage = 'Two entries of this axis share the key "%key%".';
    public $missingKeyMessage = 'Every entry of a matrix axis needs a key.';
    public $missingBoundMessage = 'Entry "%key%" needs a lower or an upper bound.';
    public $invalidBoundsMessage = 'Entry "%key%" has a lower bound greater than its upper bound.';
    public $missingValueMessage = 'Entry "%key%" needs a value.';
    public $missingAddressSourceMessage = 'A zone axis needs an address source (pickup, dropoff or task).';
    public $unknownCellMessage = 'Cell "%cell%" does not match any row and column of this matrix.';
    public $invalidCellPriceMessage = 'The price of cell "%cell%" must be a positive whole number of cents.';
    public $taskTypeOnOrderMatrixMessage = 'Only a matrix applied to each point can be restricted to pickups or dropoffs.';
    public $unknownTaskTypeMessage = 'Unknown task type "%taskType%".';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
