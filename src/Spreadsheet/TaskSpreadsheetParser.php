<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Service\Geocoder;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager;
use PhpUnitsOfMeasure\PhysicalQuantity\Mass;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class TaskSpreadsheetParser extends AbstractSpreadsheetParser
{
    use ParsePackagesTrait;

    private $geocoder;
    private $slugify;
    private $userManager;
    private $phoneNumberUtil;
    private $countryCode;
    private $entityManager;

    public function __construct(
        Geocoder $geocoder,
        SlugifyInterface $slugify,
        PhoneNumberUtil $phoneNumberUtil,
        UserManager $userManager,
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
                'assign' => '',
                'weight' => '',
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
                'assign' => 'username:' . date('Y-m-d'),
                'weight' => '50.0',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function parseData(array $data, array $options = []): array
    {
        $tasks = [];
        $tasksGroups = [];

        $defaultDate = new \DateTime('now');
        if (isset($options['date'])) {
            $defaultDate = $options['date'] instanceof \DateTime ? $options['date'] : new \DateTime($options['date']);
        }

        foreach ($data as $record) {

            [ $after, $before ] = self::parseTimeWindow($record, $defaultDate);

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
            $task->setAfter($after);
            $task->setBefore($before);

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

            if (isset($record['weight']) && !empty($record['weight']) && $task->isDropoff()) {
                $this->applyWeight($task, $record['weight']);
            }

            if (isset($record['group']) && !empty(trim($record['group']))) {
                $taskGroup = $this->getOrCreateTaskGroup($record['group'], $tasksGroups);
                $task->setGroup($taskGroup);
                $tasksGroups[$taskGroup->getName()] = $taskGroup;
            }

            $tasks[] = $task;
        }

        return $tasks;
    }

    public static function parseTimeWindow(array $record, \DateTime $defaultDate)
    {
        $isAfterNotEmpty = isset($record['after']) && !empty(trim($record['after']));
        $isBeforeNotEmpty = isset($record['before']) && !empty(trim($record['before']));
        $isTimeslotNotEmpty = isset($record['timeslot']) && !empty(trim($record['timeslot']));

        if ($isAfterNotEmpty && $isBeforeNotEmpty && $isTimeslotNotEmpty) {
            throw new \Exception('You may provide a "after" and "before" columns, or a "timeslot" column, not both');
        }

        if ($isTimeslotNotEmpty) {
            return DateParser::parseTimeslot($record['timeslot']);
        }

        // Default fallback values
        $after = clone $defaultDate;
        $after->setTime(00, 00);

        $before = clone $defaultDate;
        $before->setTime(23, 59);

        if (isset($record['after'])) {
            DateParser::parseDate($after, $record['after']);
            DateParser::parseTime($after, $record['after']);
        }

        if (isset($record['before'])) {
            DateParser::parseDate($before, $record['before']);
            DateParser::parseTime($before, $record['before']);
        }

        return [ $after, $before ];
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

        if (!DateParser::matchesDatePattern($date)) {
            throw new \Exception(sprintf('Date "%s" is not valid', $date));
        }

        $assignAt = new \DateTime();
        DateParser::parseDate($assignAt, $date);

        return [ $user, $assignAt ];
    }

    private function getOrCreateTaskGroup($groupName, $tasksGroups)
    {
        if (array_key_exists($groupName, $tasksGroups)) {
            return $tasksGroups[$groupName];
        }

        $taskGroup = new TaskGroup();
        $taskGroup->setName($groupName);

        $this->entityManager->persist($taskGroup);

        return $taskGroup;
    }

    private function applyWeight(Task $task, $weight)
    {
        $mass = new Mass($weight, 'kg');

        return $mass->toUnit('g');
    }
}
