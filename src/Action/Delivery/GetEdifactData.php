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
            $importMessage = $task->getImportMessage();
            $parsed = is_null($importMessage) ? null : $this->edifactMessageParser->parse($importMessage);

            $reports = $task->getReports()
                ->map(fn ($report) => $this->edifactMessageParser->parseReport($report))
                ->values()
                ->all();

            if (is_null($parsed) && empty($reports)) {
                continue;
            }

            $entry = $parsed ?? [
                'reference' => $reports[0]['reference'] ?? null,
                'transporter' => $reports[0]['transporter'] ?? null,
                'messageType' => null,
                'direction' => null,
                'createdAt' => null,
                'file' => null,
                'point' => null,
                'raw' => null,
            ];

            $messages[] = array_merge($entry, [
                'taskId' => $task->getId(),
                'taskType' => $task->getType(),
                'reports' => $reports,
            ]);
        }

        return new JsonResponse(['messages' => $messages]);
    }
}
