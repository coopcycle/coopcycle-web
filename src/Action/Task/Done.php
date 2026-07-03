<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;
use Symfony\Component\HttpFoundation\Request;

class Done extends Base
{
    use DoneTrait;

    public function __construct(
        TaskManager $taskManager
    )
    {
        parent::__construct($taskManager);
    }

    public function __invoke($data, Request $request)
    {
        $task = $data;

        return $this->done($task, $request);
    }
}
