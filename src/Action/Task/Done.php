<?php

namespace AppBundle\Action\Task;

use Symfony\Component\HttpFoundation\Request;

class Done extends Base
{
    use DoneTrait;

    public function __invoke($data, Request $request)
    {
        $task = $data;

        return $this->done($task, $request);
    }
}
