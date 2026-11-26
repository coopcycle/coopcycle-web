<?php

declare(strict_types=1);

namespace AppBundle\Import;

use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use Psr\Container\ContainerInterface;

/**
 * Finds what handles an import of a given kind.
 *
 * Service locators rather than iterators: resolving the parser for the mapping screen
 * should not build everything a delivery import needs to save its orders.
 */
class ImportRegistry
{
    public function __construct(
        private readonly ContainerInterface $handlers,
        private readonly ContainerInterface $cancellers,
    )
    {
    }

    public function handlerFor(string $type): ImportHandlerInterface
    {
        if (!$this->handlers->has($type)) {
            throw new \InvalidArgumentException(sprintf('No import handler for type "%s"', $type));
        }

        return $this->handlers->get($type);
    }

    public function cancellerFor(string $type): ImportCancellerInterface
    {
        if (!$this->cancellers->has($type)) {
            throw new \InvalidArgumentException(sprintf('What an import of type "%s" creates can not be cancelled', $type));
        }

        return $this->cancellers->get($type);
    }

    public function parserFor(string $type): AbstractSpreadsheetParser
    {
        return $this->handlerFor($type)->getParser();
    }
}
