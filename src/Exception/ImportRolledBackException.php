<?php

declare(strict_types=1);

namespace AppBundle\Exception;

/**
 * Saving what a file held failed: none of it was saved, and the import is marked as failed
 */
class ImportRolledBackException extends \RuntimeException
{
}
