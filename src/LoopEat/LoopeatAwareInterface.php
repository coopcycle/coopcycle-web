<?php

namespace AppBundle\LoopEat;

interface LoopeatAwareInterface
{
    /**
     * @return array
     */
    public function getFormatsToDeliverForLoopeat(): array;

    public function getLoopeatOrderId();

    public function setLoopeatOrderId($loopeatOrderId);

    public function getLoopeatReturns();
}
