<?php

namespace AppBundle\Spreadsheet\Column;

use AppBundle\Spreadsheet\DateParser;
use AppBundle\Spreadsheet\SpreadsheetPreview;
use Cocur\Slugify\SlugifyInterface;
use libphonenumber\PhoneNumberUtil;

/**
 * Suggests which field each column of a file holds, to fill the mapping screen in.
 *
 * A wrong suggestion the user accepts without looking is worse than no suggestion at all,
 * so this only guesses what it can tell for sure:
 *
 * - the column's name, either one of ours or a name the field is commonly known by;
 * - failing that, the values, and only for the types that can be told apart by looking
 *   at them, and only when a single field of that type is left to assign.
 *
 * In particular it never guesses which of pickup and dropoff a column is about from its
 * position in the file: a dropoff time slot silently imported as the pickup's is both easy
 * to produce and hard to notice.
 */
class ColumnDetector
{
    public function __construct(
        private SlugifyInterface $slugify,
        private PhoneNumberUtil $phoneNumberUtil,
        private string $countryCode,
    ) {}

    /**
     * @param ColumnDefinition[] $definitions
     */
    public function detect(SpreadsheetPreview $preview, array $definitions): ColumnMapping
    {
        $header = $preview->getHeader();

        $mapping = [];
        /** @var string[] $claimed the fields already assigned to a column */
        $claimed = [];

        // By name first: a column that says what it is, is what it says
        foreach ($header as $index => $name) {
            $definition = $this->matchByName($name, $definitions, $claimed);

            if (is_null($definition)) {
                continue;
            }

            $mapping[$index] = $definition->key;
            $claimed[] = $definition->key;
        }

        // Then by value, for the columns whose name said nothing
        foreach ($header as $index => $name) {
            if (isset($mapping[$index])) {
                continue;
            }

            $definition = $this->matchByValues($preview->getSampleValues($index), $definitions, $claimed);

            if (is_null($definition)) {
                continue;
            }

            $mapping[$index] = $definition->key;
            $claimed[] = $definition->key;
        }

        return ColumnMapping::fromArray($mapping);
    }

    /**
     * @param ColumnDefinition[] $definitions
     * @param string[] $claimed
     */
    private function matchByName(string $name, array $definitions, array $claimed): ?ColumnDefinition
    {
        $name = trim($name);

        if ('' === $name) {
            return null;
        }

        $available = array_filter($definitions, fn (ColumnDefinition $d) => !in_array($d->key, $claimed, true));

        // Our own column names, as the example file and every file exported from here has them
        foreach ($available as $definition) {
            if ($name === $definition->key) {
                return $definition;
            }
        }

        $normalized = $this->normalize($name);

        if ('' === $normalized) {
            return null;
        }

        foreach ($available as $definition) {
            if ($normalized === $this->normalize($definition->key)) {
                return $definition;
            }

            foreach ($definition->aliases as $alias) {
                if ($normalized === $this->normalize($alias)) {
                    return $definition;
                }
            }
        }

        return null;
    }

    /**
     * The field a column's values can only belong to, if there is exactly one.
     *
     * @param string[] $values
     * @param ColumnDefinition[] $definitions
     * @param string[] $claimed
     */
    private function matchByValues(array $values, array $definitions, array $claimed): ?ColumnDefinition
    {
        if (empty($values)) {
            return null;
        }

        $type = $this->sniffType($values);

        if (is_null($type)) {
            return null;
        }

        $candidates = array_values(array_filter(
            $definitions,
            fn (ColumnDefinition $d) => $d->type === $type && !in_array($d->key, $claimed, true)
        ));

        // Several fields hold this type and none of them is spoken for: which one this column
        // is, is anyone's guess, and guessing wrong is worse than leaving it to the user
        if (1 !== count($candidates)) {
            return null;
        }

        return $candidates[0];
    }

    /**
     * What every value of a column looks like, where that is a type of its own.
     *
     * Read with the very code that parses and validates these values when the file is
     * imported, so a column detected as a time slot is one the import will accept.
     *
     * @param string[] $values
     */
    private function sniffType(array $values): ?ColumnType
    {
        foreach ([ColumnType::Timeslot, ColumnType::Phone, ColumnType::Packages] as $type) {
            $matches = array_filter($values, fn (string $value) => $this->looksLike($type, $value));

            if (count($matches) === count($values)) {
                return $type;
            }
        }

        return null;
    }

    private function looksLike(ColumnType $type, string $value): bool
    {
        $value = trim($value);

        if ('' === $value) {
            return false;
        }

        switch ($type) {
            case ColumnType::Timeslot:
                try {
                    DateParser::parseTimeslot($value);

                    return true;
                } catch (\Exception $e) {
                    return false;
                }

            case ColumnType::Phone:
                try {
                    // Only that it could be a phone number, not that it is a number in use:
                    // the import itself settles for parsing it, and real files are full of
                    // numbers that parse but aren't assigned to anyone
                    return $this->phoneNumberUtil->isPossibleNumber(
                        $this->phoneNumberUtil->parse($value, strtoupper($this->countryCode))
                    );
                } catch (\Exception $e) {
                    return false;
                }

            case ColumnType::Packages:
                foreach (explode(' ', preg_replace("/[[:blank:]]+/", ' ', $value)) as $package) {
                    if (1 !== preg_match('/^[^=]+=\d+$/', $package)) {
                        return false;
                    }
                }

                return true;

            default:
                return false;
        }
    }

    /**
     * Lowercased, without accents or punctuation, so that "Adresse enlèvement",
     * "adresse_enlevement" and "ADRESSE ENLEVEMENT" are one and the same column name.
     */
    private function normalize(string $value): string
    {
        return $this->slugify->slugify($value);
    }
}
