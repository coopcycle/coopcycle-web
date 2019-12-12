<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use Cocur\Slugify\SlugifyInterface;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Type;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class DeliverySpreadsheetParser
{
    const DATE_PATTERN_HYPHEN = '/(?<year>[0-9]{4})?-?(?<month>[0-9]{2})-(?<day>[0-9]{2})/';
    const DATE_PATTERN_SLASH = '#(?<day>[0-9]{2})/(?<month>[0-9]{2})/?(?<year>[0-9]{4})?#';
    const TIME_PATTERN = '/(?<hour>[0-9]{1,2})[:hH]+(?<minute>[0-9]{1,2})?/';

    const TIME_RANGE_PATTERN = '[0-9]{2,4}[-/]?[0-9]{2,4}[-/]?[0-9]{2,4} [0-9]{2}[:hH]?[0-9]{2}';

    const MIME_TYPE_ODS = [
        'application/vnd.oasis.opendocument.spreadsheet'
    ];
    const MIME_TYPE_CSV = [
        'text/plain'
    ];
    const MIME_TYPE_XLSX = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/octet-stream'
    ];

    private $geocoder;
    private $tagManager;
    private $slugify;
    private $phoneNumberUtil;
    private $countryCode;

    public function __construct(
        Geocoder $geocoder,
        TagManager $tagManager,
        SlugifyInterface $slugify,
        PhoneNumberUtil $phoneNumberUtil,
        $countryCode)
    {
        $this->geocoder = $geocoder;
        $this->tagManager = $tagManager;
        $this->slugify = $slugify;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->countryCode = $countryCode;
    }

    /**
     * @throws IOException
     * @throws NumberParseException
     */
    public function parse($filename)
    {
        $reader = $this->createReader($filename);

        $reader->open($filename);

        $data = [];
        $header = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    $header = $row->toArray();
                    continue;
                }

                // Verify that the row is not completely empty
                if (0 === count(array_filter($row->toArray()))) {
                    continue;
                }

                $data[] = $row->toArray();
            }
        }

        $this->validateHeader($header);

        $data = array_map(function ($row) use ($header) {

            // Fix the file structure if some columns are "merged"
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            return array_combine($header, $row);
        }, $data);

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

            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    private function createReader($filename)
    {
        $mimeType = mime_content_type($filename);

        if (in_array($mimeType, self::MIME_TYPE_CSV)) {
            return $this->createCsvReader($filename);
        }

        if (in_array($mimeType, self::MIME_TYPE_ODS)) {
            return ReaderEntityFactory::createODSReader();
        }

        if (in_array($mimeType, self::MIME_TYPE_XLSX)) {
            return ReaderEntityFactory::createXLSXReader();
        }

        throw new \Exception('Unsupported file type');
    }

    private function createCsvReader($filename)
    {
        $csvReader = ReaderEntityFactory::createCSVReader();
        $csvReader->setFieldDelimiter($this->getCsvDelimiter($filename));

        return $csvReader;
    }

    private function getCsvDelimiter($filename)
    {
        $delimiters = array(
            ';' => 0,
            ',' => 0,
            "\t" => 0,
            '|' => 0,
        );

        $handle = fopen($filename, "r");
        $firstLine = fgets($handle);
        fclose($handle);

        foreach ($delimiters as $delimiter => &$count) {
            $count = count(str_getcsv($firstLine, $delimiter));
        }

        return array_search(max($delimiters), $delimiters);
    }

    private function validateHeader(array $header)
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

    private function applyTags(TaggableInterface $task, $tagsAsString)
    {
        $tagsAsString = trim($tagsAsString);

        if (!empty($tagsAsString)) {
            $slugs = explode(' ', $tagsAsString);
            $slugs = array_map([$this->slugify, 'slugify'], $slugs);
            $tags = $this->tagManager->fromSlugs($slugs);
            $task->setTags($tags);
        }
    }
}
