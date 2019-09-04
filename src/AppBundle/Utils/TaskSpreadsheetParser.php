<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use Cocur\Slugify\SlugifyInterface;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Type;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class TaskSpreadsheetParser
{
    const DATE_PATTERN_HYPHEN = '/(?<year>[0-9]{4})?-?(?<month>[0-9]{2})-(?<day>[0-9]{2})/';
    const DATE_PATTERN_SLASH = '#(?<day>[0-9]{2})/(?<month>[0-9]{2})/?(?<year>[0-9]{4})?#';
    const TIME_PATTERN = '/(?<hour>[0-9]{1,2})[:hH]+(?<minute>[0-9]{1,2})?/';

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
    public function parse($filename, \DateTime $defaultDate = null)
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

        $tasks = [];

        if (null === $defaultDate) {
            $defaultDate = new \DateTime();
        }

        foreach ($data as $record) {

            [ $doneAfter, $doneBefore ] = $this->parseTimeWindow($record, $defaultDate);

            if (isset($record['address.streetAddress'])) {
                if (!$address = $this->geocoder->geocode($record['address.streetAddress'])) {
                    // TODO Translate
                    throw new \Exception(sprintf('Could not geocode address %s', $record['address.streetAddress']));
                }
            }

            if (isset($record['address.latlng'])) {
                [ $latitude, $longitude ] = array_map('floatval', explode(',', $record['address.latlng']));
                if (!$address = $this->geocoder->reverse($latitude, $longitude)) {
                    // TODO Translate
                    throw new \Exception(sprintf('Could not reverse geocode %s', $record['address.latlng']));
                }
            }

            if (isset($record['address.name']) && !empty($record['address.name'])) {
                $address->setName($record['address.name']);
            }

            if (isset($record['address.description']) && !empty($record['address.description'])) {
                $address->setDescription($record['address.description']);
            }

            if (isset($record['address.floor']) && !empty($record['address.floor'])) {
                $address->setFloor($record['address.floor']);
            }

            if (isset($record['address.telephone']) && !empty($record['address.telephone'])) {
                /* @throws NumberParseException */
                $phoneNumber = $this->phoneNumberUtil->parse($record['address.telephone'], strtoupper($this->countryCode));
                $address->setTelephone($phoneNumber);
            }

            $task = new Task();
            $task->setAddress($address);
            $task->setDoneAfter($doneAfter);
            $task->setDoneBefore($doneBefore);

            if (isset($record['type'])) {
                $this->applyType($task, $record['type']);
            }

            if (isset($record['tags'])) {
                $this->applyTags($task, $record['tags']);
            }

            if (isset($record['comments']) && !empty($record['comments'])) {
                $task->setComments($record['comments']);
            }

            $tasks[] = $task;
        }

        return $tasks;
    }

    private function createReader($filename)
    {
        $mimeType = mime_content_type($filename);

        if (in_array($mimeType, self::MIME_TYPE_CSV)) {
            return ReaderEntityFactory::createCSVReader();
        }

        if (in_array($mimeType, self::MIME_TYPE_ODS)) {
            return ReaderEntityFactory::createODSReader();
        }

        if (in_array($mimeType, self::MIME_TYPE_XLSX)) {
            return ReaderEntityFactory::createXLSXReader();
        }

        throw new \Exception('Unsupported file type');
    }

    private function validateHeader(array $header)
    {
        $hasAddress = in_array('.streetAddress', $header);
        $hasLatLong = in_array('address.latlng', $header);

        if (!$hasAddress && !$hasLatLong) {
            throw new \Exception('You must provide an ".streetAddress" or a "address.latlng" column');
        }

        if ($hasAddress && $hasLatLong) {
            throw new \Exception('You must provide an ".streetAddress" or a "address.latlng" column, not both');
        }
    }

    private function parseTimeWindow(array $record, \DateTime $defaultDate)
    {
        // Default fallback values
        $doneAfter = clone $defaultDate;
        $doneAfter->setTime(00, 00);

        $doneBefore = clone $defaultDate;
        $doneBefore->setTime(23, 59);

        if (isset($record['after'])) {
            $this->parseDate($doneAfter, $record['after']);
            $this->parseTime($doneAfter, $record['after']);

        }

        if (isset($record['before'])) {
            $this->parseDate($doneBefore, $record['before']);
            $this->parseTime($doneBefore, $record['before']);
        }

        return [ $doneAfter, $doneBefore ];
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

    private function applyType(Task $task, $type)
    {
        $type = strtoupper($type);

        if ($type === Task::TYPE_PICKUP) {
            $task->setType(Task::TYPE_PICKUP);
        }

        if ($type === Task::TYPE_DROPOFF) {
            $task->setType(Task::TYPE_DROPOFF);
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
