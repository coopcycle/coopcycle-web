<?php

namespace AppBundle\JWT\Validation\Constraint;

use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\ConstraintViolation;

final class PermittedForOneOf implements Constraint
{
    private array $audiences;

    public function __construct(string ...$audiences)
    {
        $this->audiences = $audiences;
    }

    public function assert(Token $token): void
    {
        foreach ($this->audiences as $audience) {
            if ($token->isPermittedFor($audience)) {
                return;
            }
        }

        throw new ConstraintViolation(
            'The token is not allowed to be used by this audience'
        );
    }
}
