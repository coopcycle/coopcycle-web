<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Type;
use Cocur\Slugify\SlugifyInterface;
use FOS\UserBundle\Model\UserManagerInterface;
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
    private $userManager;
    private $phoneNumberUtil;
    private $countryCode;

    public function __construct(
        Geocoder $geocoder,
        TagManager $tagManager,
        SlugifyInterface $slugify,
        PhoneNumberUtil $phoneNumberUtil,
        UserManagerInterface $userManager,
        string $countryCode)
    {
        $this->geocoder = $geocoder;
        $this->tagManager = $tagManager;
        $this->slugify = $slugify;
        $this->userManager = $userManager;
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

            $address = null;

            if (isset($record['address'])) {
                if (!$address = $this->geocoder->geocode($record['address'])) {
                    // TODO Translate
                    throw new \Exception(sprintf('Could not geocode address %s', $record['address']));
                }
            }

            if (isset($record['latlong'])) {
                [ $latitude, $longitude ] = array_map('floatval', explode(',', $record['latlong']));
                if (!$address = $this->geocoder->reverse($latitude, $longitude)) {
                    // TODO Translate
                    throw new \Exception(sprintf('Could not reverse geocode %s', $record['latlong']));
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

            if (isset($record['assign']) && !empty(trim($record['assign']))) {
                [ $user, $assignAt ] = $this->extractAssign($record['assign']);
                $task->assignTo($user, $assignAt);
            }

            $tasks[] = $task;
        }

        return $tasks;
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
        $hasAddress = in_array('address', $header);
        $hasLatLong = in_array('latlong', $header);

        if (!$hasAddress && !$hasLatLong) {
            throw new \Exception('You must provide an "address" or a "latlong" column');
        }

        if ($hasAddress && $hasLatLong) {
            throw new \Exception('You must provide an "address" or a "latlong" column, not both');
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

    private function matchesDatePattern($text)
    {
        $hyphen = preg_match(self::DATE_PATTERN_HYPHEN, $text);
        $slash = preg_match(self::DATE_PATTERN_SLASH, $text);

        return $hyphen === 1 || $slash === 1;
    }

    private function extractAssign($text)
    {
        if (false === strpos($text, ':')) {
            throw new \Exception('The column "assign" should contain a username and a date, separated by a colon');
        }

        [ $username, $date ] = explode(':', $text, 2);

        $user = $this->userManager->findUserByUsername($username);
        if (!$user) {
            throw new \Exception(sprintf('User with username "%s" does not exist', $username));
        }

        if (!$user->hasRole('ROLE_COURIER')) {
            throw new \Exception(sprintf('Can\'t assign tasks to user with username "%s"', $username));
        }

        if (!$this->matchesDatePattern($date)) {
            throw new \Exception(sprintf('Date "%s" is not valid', $date));
        }

        $assignAt = new \DateTime();
        $this->parseDate($assignAt, $date);

        return [ $user, $assignAt ];
    }
}
