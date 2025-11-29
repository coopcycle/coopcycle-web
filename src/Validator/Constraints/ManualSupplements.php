<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ManualSupplements extends Constraint
{
    public $invalidSupplementMessage = 'manual_supplements.invalid_supplement';
    public $supplementNotInStoreRuleSetMessage = 'manual_supplements.supplement_not_in_store_rule_set';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
