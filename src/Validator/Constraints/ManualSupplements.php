<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ManualSupplements extends Constraint
{
    public $invalidSupplementMessage = 'manual_supplements.invalid_supplement';
    public $supplementNotInStoreRuleSetMessage = 'manual_supplements.supplement_not_in_store_rule_set';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
