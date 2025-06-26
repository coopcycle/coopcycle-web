<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Task;

/**
 * This is a special use case for Tricargo customers using BioDeliver software.
 */
class BioDeliverProcessor implements ProcessorInterface
{
    public function __construct(private readonly ItemProvider $provider,
        private readonly ProcessorInterface $persistProcessor)
    {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Task */
        $task = $this->provider->provide($operation, $uriVariables, $context);

        if (is_array($data->address) && isset($data->address['name']) && !empty($data->address['name'])) {
            $task->getAddress()->setName($data->address['name']);
        }

        if (!empty($data->comments)) {
            $task->setComments($data->comments);
        }

       return $this->persistProcessor->process($task, $operation, $uriVariables, $context);
    }
}

