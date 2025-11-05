<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\CartItemInput;
use AppBundle\Entity\Sylius\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

class DeleteCartItemProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly OrderModifierInterface $orderModifier,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly EntityManagerInterface $entityManager)
    {}

    /**
     * @param CartItemInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var OrderItem */
        $orderItem = $this->provider->provide($operation, $uriVariables, $context);
        $order = $orderItem->getOrder();

        $this->orderModifier->removeFromOrder($order, $orderItem);
        $this->orderProcessor->process($order);

        $this->entityManager->flush();

        return $order;
    }
}

