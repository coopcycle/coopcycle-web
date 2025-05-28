<?php

namespace AppBundle\Action\Task;

use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;


class Failed extends Base
{
    public function __invoke($data, Request $request)
    {
        $task = $data;

        try {
            $this->taskManager->markAsFailed(
                $task,
                $this->getNotes($request),
                $this->getContactName($request),
                $this->getReason($request) // @deprecated failure must be set using `PUT /api/tasks/:id/incidents`
            );
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
