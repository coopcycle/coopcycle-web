<?php

declare(strict_types=1);

namespace AppBundle\Exception;

/**
 * Saving the deliveries of a file failed: none of them was saved, and the import is marked as failed
 */
class DeliveryImportRolledBackException extends \RuntimeException
{
}
