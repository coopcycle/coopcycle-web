<?php

declare(strict_types=1);

namespace AppBundle\Import;

use AppBundle\Entity\ImportQueue;

/**
 * Cancels what an import created, one at a time.
 *
 * The batching, the progress and what happens when the worker fails are the same
 * whatever was created: they live in CancelImportedItemsHandler.
 */
interface ImportCancellerInterface
{
    /**
     * The ImportQueue::TYPE_* this handles
     */
    public function getType(): string;

    /**
     * @return int[] the ids of what the import created, oldest first
     */
    public function findIds(ImportQueue $queue): array;

    /**
     * @return bool whether it is cancelled, now or already. False for what is left as
     *              is: already under way, or too far gone to be cancelled.
     */
    public function cancel(int $id, ImportQueue $queue): bool;
}
