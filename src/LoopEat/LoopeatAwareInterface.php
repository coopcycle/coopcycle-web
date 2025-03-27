<?php

namespace AppBundle\LoopEat;

interface LoopeatAwareInterface
{
    public function getFormatsToDeliverForLoopeat(): array;

    public function getLoopeatOrderId();

    public function setLoopeatOrderId($loopeatOrderId);

    public function getLoopeatReturns();

    public function getReturnsAmountForLoopeat(): int;

    public function hasLoopeatReturns();

    public function supportsLoopeat(): bool;
}
