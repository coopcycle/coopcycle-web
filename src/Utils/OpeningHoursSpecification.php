<?php

namespace AppBundle\Utils;

use AppBundle\Entity\ClosingRule;

/**
 * @see https://schema.org/OpeningHoursSpecification
 * @see https://developers.google.com/search/docs/data-types/local-business#business_hours
 */
class OpeningHoursSpecification implements \JsonSerializable
{
    public $dayOfWeek = [];
    public $opens;
    public $closes;
    public $validFrom;
    public $validThrough;

    private static $daysOfWeek = [
        'Mo' => 'Monday',
        'Tu' => 'Tuesday',
        'We' => 'Wednesday',
        'Th' => 'Thursday',
        'Fr' => 'Friday',
        'Sa' => 'Saturday',
        'Su' => 'Sunday'
    ];

    private static function parseOpeningHours($text)
    {
        preg_match('/(Mo|Tu|We|Th|Fr|Sa|Su)+(?:[,-](Mo|Tu|We|Th|Fr|Sa|Su)*)*/', $text, $matches);

        $days = count($matches) > 0 ? $matches[0] : '';

        $openingHoursSpecification = new self();

        if (strlen($days) > 0) {
            // It is a single day
            if (false === strpos($days, '-') && false === strpos($days, ',')) {
                $openingHoursSpecification->dayOfWeek[] = self::$daysOfWeek[$days];
            } else {
                foreach (explode(',', $days) as $part) {
                    if (false !== strpos($part, '-')) {
                        [ $start, $end ] = explode('-', $part);
                        $append = false;
                        foreach (self::$daysOfWeek as $short => $long) {
                            if ($start === $short) {
                                $append = true;
                            }
                            if ($append) {
                                $openingHoursSpecification->dayOfWeek[] = $long;
                            }
                            if ($end === $short) {
                                $append = false;
                            }
                        }
                    } else {
                        $openingHoursSpecification->dayOfWeek[] = self::$daysOfWeek[$part];
                    }
                }
            }
        }

        preg_match('/([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})/', $text, $matches);

        $openingHoursSpecification->opens = $matches[1];
        $openingHoursSpecification->closes = $matches[2];

        return $openingHoursSpecification;
    }

    public static function fromOpeningHours(array $openingHours)
    {
        $openingHoursSpecification = [];

        foreach ($openingHours as $text) {
            $openingHoursSpecification[] = self::parseOpeningHours($text);
        }

        return $openingHoursSpecification;
    }

    public static function fromClosingRule(ClosingRule $closingRule)
    {
        $openingHoursSpecification = new self();

        $openingHoursSpecification->validFrom = $closingRule->getStartDate()->format('Y-m-d');
        $openingHoursSpecification->validThrough = $closingRule->getEndDate()->format('Y-m-d');

        $openingHoursSpecification->closes = $closingRule->getStartDate()->format('H:i');
        $openingHoursSpecification->opens = $closingRule->getEndDate()->format('H:i');

        return $openingHoursSpecification;
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            '@type'     => 'OpeningHoursSpecification',
            'opens'     => $this->opens,
            'closes'    => $this->closes
        ];

        if (!empty($this->dayOfWeek)) {
            $data['dayOfWeek'] = $this->dayOfWeek;
        }

        if ($this->validFrom) {
            $data['validFrom'] = $this->validFrom;
        }

        if ($this->validThrough) {
            $data['validThrough'] = $this->validThrough;
        }

        return $data;
    }
}
