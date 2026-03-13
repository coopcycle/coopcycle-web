<?php

namespace AppBundle\Payment;

final class ResolvedPaymentMethod
{
    public function __construct(
        private string $type,
        /** @var array<string, mixed> */
        private array $choiceAttr = []
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getChoiceAttr(): array
    {
        return $this->choiceAttr;
    }
}

