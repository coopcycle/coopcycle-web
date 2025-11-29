<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

trait DoneTrait
{
    protected function done(Task $task, Request $request)
    {
        try {
            $this->taskManager->markAsDone($task, $this->getNotes($request), $this->getContactName($request));
        } catch (HandlerFailedException $e) {
            $child = $e->getPrevious();

            if ($child instanceof PreviousTaskNotCompletedException || $child instanceof TaskAlreadyCompletedException || $child instanceof TaskCancelledException) {
                throw new BadRequestHttpException($child->getMessage());
            } else {
                throw $e;
            }
        }

        return $task;
    }
}
