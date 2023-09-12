<?php

namespace AppBundle\Action\Task;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Reschedule extends Base
{
    public function __invoke($data, Request $request)
    {
        try {
            $rescheduledAfter = $this->getDateTimeKey($request, 'after');
            $rescheduledBefore = $this->getDateTimeKey($request, 'before');
            $this->taskManager->reschedule($data, $rescheduledAfter, $rescheduledBefore);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $data;
    }
}
