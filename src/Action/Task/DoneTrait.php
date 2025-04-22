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
            // TODO Use StateMachine?
            if ($task->isCompleted()) {
                throw new TaskAlreadyCompletedException(sprintf('Task #%d is already completed', $task->getId()));
            }

            if ($task->isCancelled()) {
                throw new TaskCancelledException(sprintf('Task #%d is cancelled', $task->getId()));
            }

            if ($task->hasPrevious() && !$task->getPrevious()->isCompleted()) {
                // TODO : should be translated client side
                throw new PreviousTaskNotCompletedException(
                    $this->translator->trans('tasks.mark_as_done.has_previous', [
                        '%failed_task%' => $task->getId(),
                        '%previous_task%' => $task->getPrevious()->getId(),
                    ])
                );
            }

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
