<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Service\TaskManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Pick
{
    private $taskManager;

    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke(Delivery $data, Request $request)
    {
        $payload = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $payload = json_decode($content, true);
        }

        $notes = isset($payload['comments']) ? $payload['comments'] : '';

        $this->taskManager->markAsDone($data->getPickup(), $notes);

        return $data;
    }
}
