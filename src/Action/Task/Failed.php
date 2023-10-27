<?php

namespace AppBundle\Action\Task;

use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

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
                $this->getReason($request)
            );
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
