<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\EdenredCredentialsInput;
use AppBundle\Entity\Sylius\Order;

class EdenredCredentialsProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemProvider $itemProvider,
        private ProcessorInterface $persistProcessor)
    {}

    /**
     * @param EdenredCredentialsInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $order = $this->itemProvider->provide($operation, $uriVariables, $context);

        $customer = $order->getCustomer();

        $customer->setEdenredAccessToken($data->accessToken);
        $customer->setEdenredRefreshToken($data->refreshToken);

        return $this->persistProcessor->process($order, $operation, $uriVariables, $context);
    }
}
