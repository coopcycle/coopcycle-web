<?php

namespace AppBundle\Action\Task;

use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Start extends Base
{
    public function __invoke($data, Request $request)
    {
        $task = $data;

        try {
            $this->taskManager->start($task);
        } catch (LogicException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $task;
    }
}
