<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task\Group as TaskGroup;
use Symfony\Component\HttpFoundation\Request;

/**
 * @see https://github.com/api-platform/core/issues/1482
 */
class Bulk extends Base
{
    public function __invoke($data, Request $request)
    {
        $group = new TaskGroup();
        $group->setName(sprintf('Import %s', date('d/m H:i')));

        foreach ($data as $task) {
            $group->addTask($task);
        }

        return $group;
    }
}
