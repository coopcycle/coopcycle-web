<?php

namespace AppBundle\Message;

/**
 * The constructor keeps taking the filename first and the options second: the transport
 * serialises the class with its properties, so a message dispatched just before a deploy
 * is unserialized by the new code, and anything added here has to be optional.
 */
class ImportDeliveries implements RunsImport
{
    private $filename;

    public function __construct(string $filename, protected array $options = [], private ?int $queueId = null)
    {
        $this->filename = $filename;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getQueueId(): ?int
    {
        // Null for the messages dispatched before the id was carried
        return $this->queueId ?? null;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
