<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\PaymentRefundInput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;

class OrderRefundProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly OrderManager $orderManager,
        private readonly ProcessorInterface $persistProcessor)
    {}

    /**
     * @param PaymentRefundInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Order */
        $order = $this->provider->provide($operation, $uriVariables, $context);

        $this->orderManager->refundOrder($order, $data->liableParty, $data->comments);

        return $this->persistProcessor->process($order, $operation, $uriVariables, $context);
    }
}
