<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait DoneTrait
{
    protected function done(Task $task, Request $request)
    {
        try {
            $this->taskManager->markAsDone($task, $this->getNotes($request), $this->getContactName($request));
        } catch (PreviousTaskNotCompletedException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'required_action' => 'validate_previous_task',
                'previous_task' => $task->getPrevious()->getId(),
            ], Response::HTTP_CONFLICT);
            throw new BadRequestHttpException($e->getMessage());
        } catch (TaskAlreadyCompletedException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (TaskCancelledException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $task;
    }
}
