<?php

namespace AppBundle\LoopEat;

use AppBundle\Sylius\Order\OrderInterface;

class Context
{
    public $logoUrl;
    public $name;
    public $customerAppUrl;
    public $hasCredentials = false;
    public $formats = [];

    public $creditsCountCents = 0;
    public $containersCount = 0;
    public $containersTotalAmount = 0;
    public $requiredAmount = 0;
    public $containers = [];

    public $returns = [];
    public $returnsCount = 0;
    public $returnsTotalAmount;

    public $suggestion = self::SUGGESTION_NONE;

    public const SUGGESTION_NONE = 'none';
    public const SUGGESTION_RETURNS = 'returns';
    public const SUGGESTION_ADD_CREDITS = 'add_credits';

    public function suggest(OrderInterface $order): string
    {
        $missing = $this->requiredAmount - ($this->creditsCountCents + $order->getReturnsAmountForLoopeat());
        $missing = $missing < 0 ? 0 : $missing;

        if ($this->containersCount === 0) {

            if ($missing > 0) {

                return self::SUGGESTION_ADD_CREDITS;
            }

            return self::SUGGESTION_NONE;
        }

        if ($this->containersCount > 0) {

            if ($order->getReturnsAmountForLoopeat() >= $this->requiredAmount) {
                return self::SUGGESTION_NONE;
            }

            return self::SUGGESTION_RETURNS;
        }

        return self::SUGGESTION_NONE;
    }
}
