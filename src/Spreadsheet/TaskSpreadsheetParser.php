<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Type;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class TaskSpreadsheetParser extends AbstractSpreadsheetParser
{
    use ParsePackagesTrait;

    const DATE_PATTERN_HYPHEN = '/(?<year>[0-9]{4})?-?(?<month>[0-9]{2})-(?<day>[0-9]{2})/';
    const DATE_PATTERN_SLASH = '#(?<day>[0-9]{1,2})/(?<month>[0-9]{1,2})/?(?<year>[0-9]{4})?#';
    const DATE_PATTERN_DOT = '#(?<day>[0-9]{1,2})\.(?<month>[0-9]{1,2})\.?(?<year>[0-9]{4})?#';
    const TIME_PATTERN = '/(?<hour>[0-9]{1,2})[:hH]+(?<minute>[0-9]{1,2})?/';

    private $geocoder;
    private $slugify;
    private $userManager;
    private $phoneNumberUtil;
    private $countryCode;

    public function __construct(
        Geocoder $geocoder,
        SlugifyInterface $slugify,
        PhoneNumberUtil $phoneNumberUtil,
        UserManagerInterface $userManager,
        string $countryCode,
        EntityManagerInterface $entityManager)
    {
        $this->geocoder = $geocoder;
        $this->slugify = $slugify;
        $this->userManager = $userManager;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->countryCode = $countryCode;
        $this->entityManager = $entityManager;
    }

    public function getExampleData(): array
    {
        return [
            [
                'type' => 'pickup',
                'address' => '1, rue de Rivoli Paris',
                'latlong' => '',
                'comments' => 'Beware of the dog',
                'tags' => 'warning fragile important',
                'address.name' => 'Acme Inc.',
                'address.description' => '',
                'address.telephone' => '+33612345678',
                'address.contactName' => '',
                'packages' => 'small-box=1 big-box=2',
            ],
            [
                'type' => 'dropoff',
                'address' => '',
                'latlong' => '48.872322,2.354433',
                'comments' => '',
                'tags' => '',
                'address.name' => '',
                'address.description' => '',
                'address.telephone' => '+33612345678',
                'address.contactName' => 'John Doe',
                'packages' => 'small-box=1 big-box=2',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function parseData(array $data, array $options = []): array
    {
        $tasks = [];

        $defaultDate = new \DateTime('now');
        if (isset($options['date'])) {
            $defaultDate = $options['date'] instanceof \DateTime ? $options['date'] : new \DateTime($options['date']);
        }

        foreach ($data as $record) {

            [ $doneAfter, $doneBefore ] = self::parseTimeWindow($record, $defaultDate);

            $address = null;

            // Using isset() in order to parse spreadsheet with both address and coordinates columns later
            $addressHeader = 'address';
            if (isset($record['address.streetAddress'])) {
                $addressHeader = 'address.streetAddress';
            }

            if (isset($record[$addressHeader]) && !empty($record[$addressHeader])) {
                if (!$address = $this->geocoder->geocode($record[$addressHeader])) {
                    // TODO Translate
                    throw new \Exception(sprintf('Could not geocode address %s', $record[$addressHeader]));
                }
            }

            $latlngHeader = 'latlong';
            if (isset($record['address.latlng'])) {
                $latlngHeader = 'address.latlng';
            }

            if (isset($record[$latlngHeader]) && !empty($record[$latlngHeader])) {
                [ $latitude, $longitude ] = array_map('floatval', explode(',', $record[$latlngHeader]));
                if (!$address = $this->geocoder->reverse($latitude, $longitude)) {
                    // TODO Translate
                    throw new \Exception(sprintf('Could not reverse geocode %s', $record[$latlngHeader]));
                }
            }

            if (isset($record['address.name']) && !empty($record['address.name'])) {
                $address->setName($record['address.name']);
            }

            if (isset($record['address.description']) && !empty($record['address.description'])) {
                $address->setDescription($record['address.description']);
            }

            // Legacy
            // address.floor does not exist anymore
            // @see https://github.com/coopcycle/coopcycle-web/issues/1351
            if (isset($record['address.floor']) && !empty($record['address.floor'])) {
                $address->setDescription(implode(' - ', array_filter([
                    $address->getDescription(),
                    $record['address.floor']
                ])));
            }

            if (isset($record['address.telephone']) && !empty($record['address.telephone'])) {
                /* @throws NumberParseException */
                $phoneNumber = $this->phoneNumberUtil->parse($record['address.telephone'], strtoupper($this->countryCode));
                $address->setTelephone($phoneNumber);
            }

            if (isset($record['address.contactName']) && !empty($record['address.contactName'])) {
                $contactName = trim($record['address.contactName']);
                if (!empty($contactName)) {
                    $address->setContactName($record['address.contactName']);
                }
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

            if (isset($record['ref']) && !empty($record['ref'])) {
                $task->setRef($record['ref']);
            }

            if (isset($record['packages']) && !empty($record['packages'])) {
                $this->parseAndApplyPackages($task, $record['packages']);
            }

            $tasks[] = $task;
        }

        return $tasks;
    }

    public function validateHeader(array $header)
    {
        $hasAddress = in_array('address', $header);
        $hasStreetAddress = in_array('address.streetAddress', $header);
        $hasLatLong = in_array('latlong', $header);
        $hasAddressLatLng = in_array('address.latlng', $header);

        if (!$hasAddress && !$hasLatLong && !$hasStreetAddress && !$hasAddressLatLng) {
            throw new \Exception('You must provide an "address" (alternatively "address.streetAddress") or a "latlong" (alternatively "address.latlng") column');
        }

        if ($hasAddress && $hasStreetAddress) {
            throw new \Exception('You must provide an "address" or a "address.streetAddress" column, not both');
        }

        if ($hasLatLong && $hasAddressLatLng) {
            throw new \Exception('You must provide an "latlong" or a "address.latlng" column, not both');
        }
    }

    public static function parseTimeWindow(array $record, \DateTime $defaultDate)
    {
        // Default fallback values
        $doneAfter = clone $defaultDate;
        $doneAfter->setTime(00, 00);

        $doneBefore = clone $defaultDate;
        $doneBefore->setTime(23, 59);

        if (isset($record['after'])) {
            self::parseDate($doneAfter, $record['after']);
            self::parseTime($doneAfter, $record['after']);
        }

        if (isset($record['before'])) {
            self::parseDate($doneBefore, $record['before']);
            self::parseTime($doneBefore, $record['before']);
        }

        return [ $doneAfter, $doneBefore ];
    }

    private static function patchXLSXDate(\DateTimeInterface $date)
    {
        // This can happen when the cell has format numeric
        if ('1899-12-30' === $date->format('Y-m-d')) {
            return $date->format('H:i:s');
        }

        return $date->format(\DateTime::W3C);
    }

    private static function parseDate(\DateTime $date, $text)
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

    private static function parseTime(\DateTime $date, $text)
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
            $tags = array_map([$this->slugify, 'slugify'], $slugs);
            $task->setTags($tags);
        }
    }

    private function matchesDatePattern($text)
    {
        $hyphen = preg_match(self::DATE_PATTERN_HYPHEN, $text);
        $slash = preg_match(self::DATE_PATTERN_SLASH, $text);
        $dot = preg_match(self::DATE_PATTERN_DOT, $text);

        return $hyphen === 1 || $slash === 1 || $dot === 1;
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
