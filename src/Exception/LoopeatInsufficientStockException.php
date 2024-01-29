<?php

namespace AppBundle\Exception;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\ConstraintViolationList;

final class LoopeatInsufficientStockException extends \Exception
{
    public function __construct(ConstraintViolationList $violations)
    {
        $this->violations = $violations;

        parent::__construct((string) $violations);
    }

    public function getViolations(): ConstraintViolationList
    {
        return $this->violations;
    }
}
