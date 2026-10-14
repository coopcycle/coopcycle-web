<?php

namespace AppBundle\Service\Loyalty;

class InsufficientLoyaltyPointsException extends \RuntimeException
{
    public function __construct(private readonly int $balance, private readonly int $pointsCost)
    {
        parent::__construct(sprintf(
            'Customer has %d point(s), %d needed',
            $balance,
            $pointsCost
        ));
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getPointsCost(): int
    {
        return $this->pointsCost;
    }
}
