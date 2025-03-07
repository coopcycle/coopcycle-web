<?php

namespace AppBundle\Exception;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\ConstraintViolationList;

final class LoopeatInsufficientStockException extends \Exception
{
    public function __construct(ConstraintViolationList $violations)
    {
        $this->violations = $violations;

        $message = '';
        foreach ($violations as $violation) {
            $message .= $violation->getMessage() . "\n";
        }

        parent::__construct($message);
    }

    public function getViolations(): ConstraintViolationList
    {
        return $this->violations;
    }
}
