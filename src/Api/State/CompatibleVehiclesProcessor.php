<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use AppBundle\Api\Dto\CompatibleVehiclesInput;
use AppBundle\Entity\Trailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class CompatibleVehiclesProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemProvider $itemProvider,
        private ProcessorInterface $persistProcessor
    )
    {}

    /**
     * @param CompatibleVehiclesInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Trailer */
        $trailer = $this->itemProvider->provide($operation, $uriVariables, $context);

        $trailer->setCompatibleVehicles($data->compatibleVehicles);

        return $this->persistProcessor->process($trailer, $operation, $uriVariables, $context);
    }

}
