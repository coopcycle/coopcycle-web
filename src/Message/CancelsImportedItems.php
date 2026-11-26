<?php

declare(strict_types=1);

namespace AppBundle\Message;

/**
 * Asks for what an import created to be cancelled.
 *
 * An interface rather than one class so that a message dispatched by an older release,
 * and still in the transport when this one is deployed, reaches the same handler: a
 * message whose class has gone is rejected before it is handled, which would leave the
 * import stuck on "cancelling" with no way for a dispatcher to ask again.
 */
interface CancelsImportedItems
{
    public function getImportId(): int;
}
