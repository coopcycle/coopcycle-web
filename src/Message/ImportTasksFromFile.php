<?php

declare(strict_types=1);

namespace AppBundle\Message;

/**
 * Imports a file of tasks through the import queue.
 *
 * A class of its own rather than ImportTasks, which the dispatch board's uploader and
 * the API's /tasks/import_async still dispatch and ImportTasksHandler still handles: one
 * message class with two handlers would run both on every message.
 */
class ImportTasksFromFile implements RunsImport
{
    public function __construct(
        private readonly string $filename,
        private readonly array $options = [],
        private readonly ?int $queueId = null,
    )
    {
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getQueueId(): ?int
    {
        return $this->queueId ?? null;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
