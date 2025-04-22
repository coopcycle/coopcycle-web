<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\LoopeatReturns;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

class LoopeatReturnsProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemProvider $provider,
        private OrderProcessorInterface $orderProcessor,
        private ProcessorInterface $persistProcessor)
    {}

    /**
     * @param LoopeatReturns $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var OrderInterface */
        $order = $this->provider->provide($operation, $uriVariables, $context);

        $order->setLoopeatReturns($data->returns);

        $this->orderProcessor->process($order);

        return $this->persistProcessor->process($order, $operation, $uriVariables, $context);
    }
}
