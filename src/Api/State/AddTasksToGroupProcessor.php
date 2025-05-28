<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ArrayOfTasksInput;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Service\TaskManager;

class AddTasksToGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TaskManager $taskManager,
        private readonly ItemProvider $provider,
        private readonly ProcessorInterface $persistProcessor)
    {}

    /**
     * @param ArrayOfTasksInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var TaskGroup */
        $taskGroup = $this->provider->provide($operation, $uriVariables, $context);

        $this->taskManager->addToGroup($data->tasks, $taskGroup);

        return $this->persistProcessor->process($taskGroup, $operation, $uriVariables, $context);
    }
}
