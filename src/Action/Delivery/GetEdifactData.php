<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Transporter\EdifactMessageParser;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns the original EDIFACT data a delivery's tasks were imported from,
 * parsed into a human-readable structure for display to administrators.
 */
class GetEdifactData
{
    public function __construct(
        private EdifactMessageParser $edifactMessageParser,
    ) {
    }

    public function __invoke(Delivery $data): JsonResponse
    {
        $messages = [];

        foreach ($data->getTasks() as $task) {
            /** @var Task $task */
            $message = $task->getImportMessage();
            if (is_null($message)) {
                continue;
            }

            $parsed = $this->edifactMessageParser->parse($message);
            if (is_null($parsed)) {
                continue;
            }

            $messages[] = array_merge($parsed, [
                'taskId' => $task->getId(),
                'taskType' => $task->getType(),
            ]);
        }

        return new JsonResponse(['messages' => $messages]);
    }
}
