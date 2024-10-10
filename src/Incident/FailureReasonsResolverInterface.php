<?php

namespace AppBundle\Incident;

use AppBundle\Entity\Task;

interface FailureReasonsResolverInterface
{
    public function supports(Task $task): bool;

    public function getFailureReasons(Task $task): array;
}
