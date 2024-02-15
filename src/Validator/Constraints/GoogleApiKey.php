<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class GoogleApiKey extends Constraint
{
    public const INVALID_API_KEY_ERROR = '13dfb729-edf0-480a-a653-69c9cafccaef';

    public $invalidApiKeyMessage = 'googlemaps.api_key.invalid';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
