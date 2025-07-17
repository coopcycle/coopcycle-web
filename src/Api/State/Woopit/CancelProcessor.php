<?php

namespace AppBundle\Api\State\Woopit;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;

class CancelProcessor implements ProcessorInterface
{
    public function __construct(
        private TaskManager $taskManager,
        private EntityManagerInterface $entityManager)
    {}

    /**
     * @param Delivery $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        foreach ($data->getTasks() as $task) {
            if ($task->isAssigned()) {
                throw new AccessDeniedHttpException('Tasks have already been assigned');
            }
        }

        foreach ($data->getTasks() as $task) {
            $this->taskManager->cancel($task);
        }

        $this->entityManager->flush();
    }
}
