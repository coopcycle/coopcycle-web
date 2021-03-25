<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
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
    private $phoneNumberUtil;
    private $countryCode;

    public function __construct(Geocoder $geocoder, PhoneNumberUtil $phoneNumberUtil, string $countryCode)
    {
        $this->geocoder = $geocoder;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->countryCode = $countryCode;
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

            [ $pickupAfter, $pickupBefore ] = $this->parseTimeRange($record['pickup.timeslot']);
            [ $dropoffAfter, $dropoffBefore ] = $this->parseTimeRange($record['dropoff.timeslot']);

            $delivery = new Delivery();

            $delivery->getPickup()->setAddress($pickupAddress);
            $delivery->getPickup()->setDoneAfter($pickupAfter);
            $delivery->getPickup()->setDoneBefore($pickupBefore);

            $delivery->getDropoff()->setAddress($dropoffAddress);
            $delivery->getDropoff()->setDoneAfter($dropoffAfter);
            $delivery->getDropoff()->setDoneBefore($dropoffBefore);

            $this->enhanceTask($delivery->getPickup(), 'pickup', $record);
            $this->enhanceTask($delivery->getDropoff(), 'dropoff', $record);

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

    private function getColumn(string $prefix, $name)
    {
        return sprintf('%s.%s', $prefix, $name);
    }

    private function enhanceTask(Task $task, string $prefix, array $record)
    {
        $addressNameColumn         = $this->getColumn($prefix, 'address.name');
        $addressDescriptionColumn  = $this->getColumn($prefix, 'address.description');
        $addressTelephoneColumn    = $this->getColumn($prefix, 'address.telephone');
        $commentsColumn            = $this->getColumn($prefix, 'comments');

        if (isset($record[$addressNameColumn]) && !empty($record[$addressNameColumn])) {
            $task->getAddress()->setName($record[$addressNameColumn]);
        }

        if (isset($record[$addressDescriptionColumn]) && !empty($record[$addressDescriptionColumn])) {
            $task->getAddress()->setDescription($record[$addressDescriptionColumn]);
        }

        if (isset($record[$addressTelephoneColumn]) && !empty($record[$addressTelephoneColumn])) {
            /* @throws NumberParseException */
            $phoneNumber = $this->phoneNumberUtil->parse($record[$addressTelephoneColumn], strtoupper($this->countryCode));
            $task->getAddress()->setTelephone($phoneNumber);
        }

        if (isset($record[$commentsColumn]) && !empty($record[$commentsColumn])) {
            $task->setComments($record[$commentsColumn]);
        }
    }

    public function getExampleData(): array
    {
        return [
            [
                'pickup.address' => '24 rue de rivoli paris',
                'pickup.address.name' => 'Awesome business',
                'pickup.address.description' => '',
                'pickup.address.telephone' => '+33612345678',
                'pickup.comments' => 'Fragile',
                'pickup.timeslot' => '2019-12-12 10:00 - 2019-12-12 11:00',
                'dropoff.address' => '58 av parmentier paris',
                'dropoff.address.name' => 'Awesome business',
                'dropoff.address.description' => 'Buzzer AB12',
                'dropoff.address.telephone' => '+33612345678',
                'dropoff.comments' => '',
                'dropoff.timeslot' => '2019-12-12 12:00 - 2019-12-12 13:00',
            ],
            [
                'pickup.address' => '24 rue de rivoli paris',
                'pickup.address.name' => 'Awesome business',
                'pickup.address.description' => '',
                'pickup.address.telephone' => '+33612345678',
                'pickup.comments' => 'Fragile',
                'pickup.timeslot' => '2019-12-12 10:00 - 2019-12-12 11:00',
                'dropoff.address' => '34 bd de magenta paris',
                'dropoff.address.name' => 'Awesome business',
                'dropoff.address.description' => 'Buzzer AB12',
                'dropoff.address.telephone' => '+33612345678',
                'dropoff.comments' => '',
                'dropoff.timeslot' => '2019-12-12 12:00 - 2019-12-12 13:00',
            ],
        ];
    }
}
