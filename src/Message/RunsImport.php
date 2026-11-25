<?php

declare(strict_types=1);

namespace AppBundle\Message;

/**
 * Asks for an uploaded file to be imported.
 *
 * An interface rather than one class per kind so that RunImportHandler can be the only
 * handler, and so that a message dispatched by an older release and still in the
 * transport reaches it: the transport serialises the class name, and a message whose
 * class has gone is rejected before it is handled, which leaves no trace on the import
 * and no event for ImportFailureSubscriber to act on.
 */
interface RunsImport
{
    public function getFilename(): string;

    /**
     * The id of the import, when the message carries one. Older messages only carry the
     * filename, which the handler falls back to.
     */
    public function getQueueId(): ?int;

    public function getOptions(): array;
}
