<?php

namespace AppBundle\Spreadsheet\Column;

/**
 * What a column is about, to group the choices on the mapping screen.
 */
enum ColumnGroup: string
{
    case Pickup = 'pickup';
    case Dropoff = 'dropoff';
    case Delivery = 'delivery';
    // A file of tasks has no pickup and dropoff pair: each row is one or the other
    case Address = 'address';
    case Task = 'task';

    public function labelKey(): string
    {
        return sprintf('import.column_group.%s', $this->value);
    }
}
