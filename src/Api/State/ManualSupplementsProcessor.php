<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\DeliveryDto;
use AppBundle\Pricing\ManualSupplement;
use AppBundle\Pricing\ManualSupplements;

class ManualSupplementsProcessor implements ProcessorInterface
{
    public function __construct(
    )
    {
    }

    /**
     * @param DeliveryDto $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ManualSupplements
    {
        // Extract manual supplements from the DTO
        $orderSupplements = [];
        if ($data->order?->manualSupplements) {
            foreach ($data->order->manualSupplements as $supplement) {
                $orderSupplements[] = new ManualSupplement($supplement->pricingRule, $supplement->quantity);
            }
        }
        return new ManualSupplements($orderSupplements);
    }

}
