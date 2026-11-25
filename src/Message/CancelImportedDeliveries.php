<?php

declare(strict_types=1);

namespace AppBundle\Message;

/**
 * @deprecated kept only for the messages dispatched before CancelImportedItems replaced
 *             it and still in the transport. Delete once they have been handled.
 */
class CancelImportedDeliveries implements CancelsImportedItems
{
    public function __construct(private readonly int $importId)
    {
    }

    public function getImportId(): int
    {
        return $this->importId;
    }
}
