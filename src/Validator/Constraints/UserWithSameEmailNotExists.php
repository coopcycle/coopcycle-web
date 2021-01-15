<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UserWithSameEmailNotExists extends Constraint
{
    const USER_WITH_SAME_EMAIL_EXISTS_ERROR = 'd775d559-0819-425a-8043-d5b5dadda6f4';

    public $message = 'checkout.user_with_same_email_exists';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
