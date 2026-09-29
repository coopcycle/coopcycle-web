<?php

declare(strict_types=1);

namespace AppBundle\Utils;

use AppBundle\Entity\Task;

/**
 * Signs the public proof of delivery URL: it is sent to transporters and
 * shows a name, an address and a signature, so the task id alone (or a
 * hashid) is not enough.
 */
class ProofOfDeliveryToken
{
    public function __construct(private string $secret)
    {
    }

    public function generate(Task $task): string
    {
        return hash_hmac('sha256', sprintf('pod:%d', $task->getId()), $this->secret);
    }

    public function isValid(Task $task, string $token): bool
    {
        return hash_equals($this->generate($task), $token);
    }
}
