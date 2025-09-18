<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\CartItemInput;
use AppBundle\Entity\Sylius\OrderItem;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

class UpdateCartItemProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly ProcessorInterface $persistProcessor)
    {}

    /**
     * @param CartItemInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var OrderItem */
        $orderItem = $this->provider->provide($operation, $uriVariables, $context);

        $this->orderItemQuantityModifier->modify($orderItem, $data->quantity);
        $this->orderProcessor->process($orderItem->getOrder());

        $this->persistProcessor->process($orderItem, $operation, $uriVariables, $context);

        return $orderItem->getOrder();
    }
}
