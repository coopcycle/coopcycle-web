<?php

namespace AppBundle\Action\Order;

use AppBundle\LoopEat\Client as LoopEatClient;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

class UpdateLoopeatReturns
{
    public function __construct(private OrderProcessorInterface $orderProcessor)
    {}

    public function __invoke($data)
    {
        $this->orderProcessor->process($data);

        return $data;
    }
}


