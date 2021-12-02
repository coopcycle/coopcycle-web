<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Task\ImportQueue as TaskImportQueue;
use Symfony\Component\HttpFoundation\Request;

/**
 * @see https://github.com/api-platform/core/issues/1482
 */
class BulkAsync extends Base
{
    public function __invoke(Request $request)
    {
        $group = new TaskGroup();
        $group->setName(sprintf('Import %s', date('d/m H:i')));

        $queue = new TaskImportQueue();
        $queue->setStatus('waiting');
        $queue->setGroup($group);

        return $queue;
    }
}
