<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Address;
use AppBundle\Entity\Store;
use Symfony\Component\HttpFoundation\RequestStack;

class StoreAddressProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemProvider $provider,
        private ProcessorInterface $persistProcessor)
    {}

    /**
     * @param Address $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $store = $this->provider->provide($operation, $uriVariables, $context);

        $store->addAddress($data);

        return $this->persistProcessor->process($store, $operation, $uriVariables, $context);
    }
}

