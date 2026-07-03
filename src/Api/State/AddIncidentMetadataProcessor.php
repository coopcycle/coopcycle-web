<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\IncidentMetadataInput;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Service\TaskManager;

class AddIncidentMetadataProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly ProcessorInterface $persistProcessor)
    {}

    /**
     * @param IncidentMetadataInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Incident */
        $incident = $this->provider->provide($operation, $uriVariables, $context);

        $incident->setMetadata(array_merge($incident->getMetadata(), $data->metadata));

        return $this->persistProcessor->process($incident, $operation, $uriVariables, $context);
    }
}
