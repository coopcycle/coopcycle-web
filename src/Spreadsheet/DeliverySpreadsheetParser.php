<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Service\Geocoder;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Type;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class DeliverySpreadsheetParser extends AbstractSpreadsheetParser
{
    const DATE_PATTERN_HYPHEN = '/(?<year>[0-9]{4})?-?(?<month>[0-9]{2})-(?<day>[0-9]{2})/';
    const DATE_PATTERN_SLASH = '#(?<day>[0-9]{2})/(?<month>[0-9]{2})/?(?<year>[0-9]{4})?#';
    const TIME_PATTERN = '/(?<hour>[0-9]{1,2})[:hH]+(?<minute>[0-9]{1,2})?/';

    const TIME_RANGE_PATTERN = '[0-9]{2,4}[-/]?[0-9]{2,4}[-/]?[0-9]{2,4} [0-9]{1,2}[:hH]?[0-9]{2}';

    private $geocoder;

    public function __construct(Geocoder $geocoder)
    {
        $this->geocoder = $geocoder;
    }

    /**
     * @inheritdoc
     */
    public function parseData(array $data, array $options = []): array
    {
        $deliveries = [];

        foreach ($data as $record) {

            if (!$pickupAddress = $this->geocoder->geocode($record['pickup.address'])) {
                // TODO Translate
                throw new \Exception(sprintf('Could not geocode %s', $record['pickup.address']));
            }

            if (!$dropoffAddress = $this->geocoder->geocode($record['dropoff.address'])) {
                // TODO Translate
                throw new \Exception(sprintf('Could not geocode %s', $record['dropoff.address']));
            }

            if (isset($record['pickup.address.name']) && !empty($record['pickup.address.name'])) {
                $pickupAddress->setName($record['pickup.address.name']);
            }

            if (isset($record['dropoff.address.name']) && !empty($record['dropoff.address.name'])) {
                $dropoffAddress->setName($record['dropoff.address.name']);
            }

            [ $pickupAfter, $pickupBefore ] = $this->parseTimeRange($record['pickup.timeslot']);
            [ $dropoffAfter, $dropoffBefore ] = $this->parseTimeRange($record['dropoff.timeslot']);

            $delivery = new Delivery();

            $delivery->getPickup()->setAddress($pickupAddress);
            $delivery->getPickup()->setDoneAfter($pickupAfter);
            $delivery->getPickup()->setDoneBefore($pickupBefore);

            $delivery->getDropoff()->setAddress($dropoffAddress);
            $delivery->getDropoff()->setDoneAfter($dropoffAfter);
            $delivery->getDropoff()->setDoneBefore($dropoffBefore);

            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    public function validateHeader(array $header)
    {
        $hasPickupAddress = in_array('pickup.address', $header);
        $hasDropoffAddress = in_array('dropoff.address', $header);

        if (!$hasPickupAddress) {
            throw new \Exception('You must provide a "pickup.address" column');
        }

        if (!$hasDropoffAddress) {
            throw new \Exception('You must provide a "dropoff.address" column');
        }
    }

    private function parseTimeRange($timeSlotAsText)
    {
        if (false === strpos($timeSlotAsText, '-')) {
            throw new \Exception(sprintf('Time range "%s" is not valid', $timeSlotAsText));
        }

        $pattern = sprintf('#^(%s)[^0-9]+(%s)$#', self::TIME_RANGE_PATTERN, self::TIME_RANGE_PATTERN);

        if (1 !== preg_match($pattern, $timeSlotAsText, $matches)) {
            throw new \Exception(sprintf('Time range "%s" is not valid', $timeSlotAsText));
        }

        $start = new \DateTime();
        $end = new \DateTime();

        $this->parseDate($start, $matches[1]);
        $this->parseTime($start, $matches[1]);

        $this->parseDate($end, $matches[2]);
        $this->parseTime($end, $matches[2]);

        return [ $start, $end ];
    }

    private function parseDate(\DateTime $date, $text)
    {
        if (1 === preg_match(self::DATE_PATTERN_HYPHEN, $text, $matches)) {
            $date->setDate(isset($matches['year']) ? $matches['year'] : $date->format('Y'), $matches['month'], $matches['day']);
        } elseif (1 === preg_match(self::DATE_PATTERN_SLASH, $text, $matches)) {
            $date->setDate(isset($matches['year']) ? $matches['year'] : $date->format('Y'), $matches['month'], $matches['day']);
        }
    }

    private function parseTime(\DateTime $date, $text)
    {
        if (1 === preg_match(self::TIME_PATTERN, $text, $matches)) {
            $date->setTime($matches['hour'], isset($matches['minute']) ? $matches['minute'] : 00);
        }
    }

    public function getExampleData(): array
    {
        return [
            [
                'pickup.address' => '24 rue de rivoli paris',
                'pickup.address.name' => 'Awesome business',
                'dropoff.address' => '58 av parmentier paris',
                'dropoff.address.name' => 'Awesome business',
                'pickup.timeslot' => '2019-12-12 10:00 - 2019-12-12 11:00',
                'dropoff.timeslot' => '2019-12-12 12:00 - 2019-12-12 13:00',
            ],
            [
                'pickup.address' => '24 rue de rivoli paris',
                'pickup.address.name' => 'Awesome business',
                'dropoff.address' => '34 bd de magenta paris',
                'dropoff.address.name' => 'Awesome business',
                'pickup.timeslot' => '2019-12-12 10:00 - 2019-12-12 11:00',
                'dropoff.timeslot' => '2019-12-12 12:00 - 2019-12-12 13:00',
            ],
        ];
    }
}
