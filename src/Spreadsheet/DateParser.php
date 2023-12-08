<?php

namespace AppBundle\Spreadsheet;

class DateParser
{
    const DATE_PATTERN_HYPHEN = '/(?<year>[0-9]{4})?-?(?<month>[0-9]{2})-(?<day>[0-9]{2})/';
    const DATE_PATTERN_SLASH = '#(?<day>[0-9]{1,2})/(?<month>[0-9]{1,2})/?(?<year>[0-9]{4})?#';
    const DATE_PATTERN_DOT = '#(?<day>[0-9]{1,2})\.(?<month>[0-9]{1,2})\.?(?<year>[0-9]{4})?#';
    const TIME_PATTERN = '/(?<hour>[0-9]{1,2})[:hH]+(?<minute>[0-9]{1,2})?/';
    const TIME_RANGE_PATTERN = '[0-9]{2,4}[-/]?[0-9]{2,4}[-/]?[0-9]{2,4} [0-9]{1,2}[:hH]?[0-9]{2}';

    public static function parseTimeslot($text)
    {
        if (!str_contains($text, '-')) {
            throw new \Exception(sprintf('"%s" is not a valid timeslot', $text));
        }

        $pattern = sprintf('#^(%s)[^0-9]+(%s)$#', self::TIME_RANGE_PATTERN, self::TIME_RANGE_PATTERN);

        if (1 !== preg_match($pattern, $text, $matches)) {
            throw new \Exception(sprintf('"%s" is not a valid timeslot', $text));
        }

        $start = new \DateTime();
        $end = new \DateTime();

        self::parseDate($start, $matches[1]);
        self::parseTime($start, $matches[1]);

        self::parseDate($end, $matches[2]);
        self::parseTime($end, $matches[2]);

        return [ $start, $end ];
    }

    public static function parseDate(\DateTime $date, $text)
    {
        if (!is_string($text)) {
            if ($text instanceof \DateTimeInterface) {
                $text = self::patchXLSXDate($text);
            }
        }

        if (1 === preg_match(self::DATE_PATTERN_HYPHEN, $text, $matches)) {
            $date->setDate(isset($matches['year']) ? $matches['year'] : $date->format('Y'), $matches['month'], $matches['day']);
        } elseif (1 === preg_match(self::DATE_PATTERN_SLASH, $text, $matches)) {
            $date->setDate(isset($matches['year']) ? $matches['year'] : $date->format('Y'), $matches['month'], $matches['day']);
        } elseif (1 === preg_match(self::DATE_PATTERN_DOT, $text, $matches)) {
            $date->setDate(isset($matches['year']) ? $matches['year'] : $date->format('Y'), $matches['month'], $matches['day']);
        }
    }

    public static function parseTime(\DateTime $date, $text)
    {
        if (!is_string($text)) {
            if ($text instanceof \DateTimeInterface) {
                $text = self::patchXLSXDate($text);
            }
        }

        if (1 === preg_match(self::TIME_PATTERN, $text, $matches)) {
            $date->setTime($matches['hour'], isset($matches['minute']) ? $matches['minute'] : 00);
        }
    }

    public static function matchesDatePattern($text)
    {
        $hyphen = preg_match(self::DATE_PATTERN_HYPHEN, $text);
        $slash = preg_match(self::DATE_PATTERN_SLASH, $text);
        $dot = preg_match(self::DATE_PATTERN_DOT, $text);

        return $hyphen === 1 || $slash === 1 || $dot === 1;
    }

    private static function patchXLSXDate(\DateTimeInterface $date)
    {
        // This can happen when the cell has format numeric
        if ('1899-12-30' === $date->format('Y-m-d')) {
            return $date->format('H:i:s');
        }

        return $date->format(\DateTime::W3C);
    }
}
