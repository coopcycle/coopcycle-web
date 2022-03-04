<?php

namespace AppBundle\Validator\Constraints;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 */
class ClosingRules extends Constraint
{
    public $message = 'closing_rules.message';
    public $closingRules;

    public function __construct($options = null)
    {
        if (null === $options) {
            $options = [
                'closingRules' => new ArrayCollection(),
            ];
        }

        if (is_array($options) && !isset($options['closingRules'])) {
            $options['closingRules'] = new ArrayCollection();
        }

        if (null !== $options && $options instanceof Collection) {
            $options = [
                'closingRules' => $options,
            ];
        }

        parent::__construct($options);
    }

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
