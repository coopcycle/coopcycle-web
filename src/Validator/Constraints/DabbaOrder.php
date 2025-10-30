<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class DabbaOrder extends Constraint
{
    public $insufficientQuantity = 'dabba.insufficient_quantity';
    public $insufficientWallet = 'dabba.insufficient_wallet';
    public $requestFailed = 'dabba.request_failed';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
