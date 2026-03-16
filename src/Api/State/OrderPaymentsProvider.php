<?php

namespace AppBundle\Api\State;

use AppBundle\Entity\Sylius\Order;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProviderInterface;

final class OrderPaymentsProvider implements ProviderInterface
{
    public function __construct(
        private ItemProvider $provider)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var Order */
        $order = $this->provider->provide($operation, $uriVariables, $context);

        return $order->getPayments();
    }
}
