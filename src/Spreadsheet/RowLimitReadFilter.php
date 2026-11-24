<?php

namespace AppBundle\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Reads the first rows of a file and no more.
 *
 * The mapping screen only shows a handful of rows, and it is on the web request: without
 * this, opening it would load a whole 20k row file into memory to show six lines of it.
 */
class RowLimitReadFilter implements IReadFilter
{
    public function __construct(private readonly int $rows)
    {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row <= $this->rows;
    }
}
