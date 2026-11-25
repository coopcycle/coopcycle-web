<?php

declare(strict_types=1);

namespace AppBundle\Message;

/**
 * Cancels what a file import created, but what is already under way
 */
class CancelImportedItems implements CancelsImportedItems
{
    public function __construct(private readonly int $importId)
    {
    }

    public function getImportId(): int
    {
        return $this->importId;
    }
}
