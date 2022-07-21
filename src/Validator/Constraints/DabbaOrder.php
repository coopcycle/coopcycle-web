<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class DabbaOrder extends Constraint
{
    public $insufficientQuantity = 'dabba.insufficient_quantity';
    public $insufficientWallet = 'dabba.insufficient_wallet';
    public $requestFailed = 'dabba.request_failed';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
