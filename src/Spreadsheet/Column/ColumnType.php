<?php

namespace AppBundle\Spreadsheet\Column;

/**
 * What a column holds, as far as the mapping screen is concerned.
 *
 * Only used to suggest a mapping and to tell the user what a field expects:
 * the parsers keep doing their own parsing and validation of the values.
 */
enum ColumnType: string
{
    case Address = 'address';
    case Timeslot = 'timeslot';
    case Phone = 'phone';
    case Number = 'number';
    case Tags = 'tags';
    case Metadata = 'metadata';
    case Packages = 'packages';
    case Text = 'text';

    /**
     * Whether a value of this type can be told apart from the other types by looking at it.
     *
     * Only these are sniffed, and only for a column no name matched: an address has no
     * reliable signature, and the free text types have none at all.
     *
     * @see \AppBundle\Spreadsheet\Column\ColumnDetector
     */
    public function isDetectableFromValues(): bool
    {
        return in_array($this, [self::Timeslot, self::Phone, self::Packages], true);
    }
}
