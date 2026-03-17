<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;

final class OrderRefundsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $itemProvider)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var Order */
        $order = $this->itemProvider->provide($operation, $uriVariables, $context);

        return array_reduce($order->getPayments()->toArray(), function ($refunds, $payment) {
            return array_merge($refunds, $payment->getRefunds()->toArray());
        }, []);
    }
}
