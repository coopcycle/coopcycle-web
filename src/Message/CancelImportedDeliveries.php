<?php

declare(strict_types=1);

namespace AppBundle\Message;

/**
 * Cancels the deliveries a file import created, but those already under way
 */
class CancelImportedDeliveries
{
    public function __construct(private readonly int $importId)
    {
    }

    public function getImportId(): int
    {
        return $this->importId;
    }
}
