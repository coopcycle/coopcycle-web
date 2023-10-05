<?php

namespace AppBundle\Action\Order;

use AppBundle\LoopEat\Client as LoopEatClient;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

class UpdateLoopeatFormats
{
    public function __construct(private OrderProcessorInterface $orderProcessor, private LoopEatClient $client)
    {}

    public function __invoke($data)
    {
        $this->orderProcessor->process($data);
        $this->client->updateDeliverFormats($data);

        return $data;
    }
}

