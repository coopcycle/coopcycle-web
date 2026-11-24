<?php

declare(strict_types=1);

namespace AppBundle\Exception;

/**
 * Thrown from inside the spreadsheet parser to stop reading a file that a
 * dispatcher cancelled the import of.
 */
class DeliveryImportCancelledException extends \Exception
{
}
