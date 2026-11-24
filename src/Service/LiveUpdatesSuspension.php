<?php

declare(strict_types=1);

namespace AppBundle\Service;

/**
 * Stops the live updates sent for each task and each tour, while a job saves
 * a lot of them at once. The job sends a single event instead, upon which the
 * dispatch board reloads what it shows.
 *
 * Importing a file of 15k deliveries sent a "task:created" per task, a
 * "tour:updated" per task added to a tour, and the notifications that go with
 * them: a dozen calls to Centrifugo per line, while the import holds its
 * transaction open.
 */
class LiveUpdatesSuspension
{
    private bool $suspended = false;

    /**
     * @template T
     * @param callable(): T $job
     * @return T
     */
    public function during(callable $job): mixed
    {
        $this->suspended = true;

        try {
            return $job();
        } finally {
            $this->suspended = false;
        }
    }

    public function isSuspended(): bool
    {
        return $this->suspended;
    }
}
