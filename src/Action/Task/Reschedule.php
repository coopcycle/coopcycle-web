<?php

namespace AppBundle\Action\Task;

use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Reschedule extends Base
{
    public function __invoke($data, Request $request)
    {
        $task = $data;

        try {
            $this->taskManager->reschedule($task, $this->getRescheduleAt($request));
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $task;
    }
}
