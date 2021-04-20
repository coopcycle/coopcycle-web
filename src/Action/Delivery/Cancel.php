<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Service\TaskManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Cancel
{
    private $taskManager;

    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke(Delivery $data, Request $request)
    {
        // It would have been cleaner to implement it via a validator,
        // but DELETE operations do not trigger validation
        // @see ApiPlatform\Core\Validator\EventListener\ValidateListener
        foreach ($data->getTasks() as $task) {
            if ($task->isAssigned()) {
                throw new BadRequestHttpException('Tasks have already been assigned');
            }
        }

        foreach ($data->getTasks() as $task) {
            $this->taskManager->cancel($task);
        }

        return $data;
    }
}
