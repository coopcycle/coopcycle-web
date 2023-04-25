<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait DoneTrait
{
    protected function done(Task $task, Request $request)
    {
        try {
            $this->taskManager->markAsDone($task, $this->getNotes($request), $this->getContactName($request));
        } catch (PreviousTaskNotCompletedException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (TaskAlreadyCompletedException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (TaskCancelledException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $task;
    }
}
