<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use AppBundle\Spreadsheet\Column\ColumnDefinition;
use AppBundle\Spreadsheet\Column\ColumnGroup;
use AppBundle\Spreadsheet\Column\ColumnType;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager;
use PhpUnitsOfMeasure\PhysicalQuantity\Mass;
use Symfony\Contracts\Translation\TranslatorInterface;
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
    /**
     * Only read through: ParsePackagesTrait looks the packages up. Nothing here writes.
     */
    private $entityManager;

    /**
     * Who each "assign" value names, or what is wrong with it, looked up once per file
     *
     * @var array<string,array{user?: mixed, date?: \DateTime, error?: string}>
     */
    private array $assignees = [];

    public function __construct(
        Geocoder $geocoder,
        SlugifyInterface $slugify,
        PhoneNumberUtil $phoneNumberUtil,
        UserManager $userManager,
        string $countryCode,
        EntityManagerInterface $entityManager,
        private TranslatorInterface $translator)
    {
        $this->geocoder = $geocoder;
        $this->slugify = $slugify;
        $this->userManager = $userManager;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->countryCode = $countryCode;
        $this->entityManager = $entityManager;
    }

    /**
     * The fields a file of tasks can be mapped to.
     *
     * The keys are the names the header row has always used, so that a mapped file and
     * one read by its header are read exactly the same way. address.streetAddress,
     * address.latlng and address.floor stay header-only spellings: they are aliases
     * here, so a file whose header uses them is still recognised.
     */
    public function getColumns(): array
    {
        return [
            new ColumnDefinition('address', ColumnType::Address, ColumnGroup::Address, false, [
                'address.streetaddress', 'street address', 'delivery address', 'destination',
                'adresse', 'adresse de livraison', 'lieu',
                'direccion', 'direccion de entrega',
            ]),
            new ColumnDefinition('latlong', ColumnType::Text, ColumnGroup::Address, false, [
                'address.latlng', 'latlng', 'lat long', 'latitude longitude', 'coordinates', 'gps',
                'coordonnees', 'coordonnees gps',
                'coordenadas',
            ]),
            new ColumnDefinition('address.name', ColumnType::Text, ColumnGroup::Address, false, [
                'name', 'customer', 'customer name', 'company',
                'nom', 'client', 'nom du client', 'societe',
                'nombre', 'cliente',
            ]),
            new ColumnDefinition('address.description', ColumnType::Text, ColumnGroup::Address, false, [
                'address description', 'address details', 'details', 'door code', 'address.floor', 'floor',
                'complement adresse', 'complement', 'digicode', 'etage',
                'detalles', 'complemento',
            ]),
            new ColumnDefinition('address.telephone', ColumnType::Phone, ColumnGroup::Address, false, [
                'telephone', 'phone', 'phone number', 'customer phone',
                'tel', 'numero de telephone', 'telephone client',
                'telefono',
            ]),
            new ColumnDefinition('address.contactName', ColumnType::Text, ColumnGroup::Address, false, [
                'contact', 'contact name', 'recipient',
                'nom du contact', 'contact sur place', 'destinataire',
                'nombre de contacto', 'destinatario',
            ]),
            new ColumnDefinition('type', ColumnType::Text, ColumnGroup::Task, false, [
                'task type', 'pickup or dropoff',
                'type de tache', 'enlevement ou livraison',
                'tipo', 'tipo de tarea',
            ]),
            new ColumnDefinition('timeslot', ColumnType::Timeslot, ColumnGroup::Task, false, [
                'time slot', 'slot', 'time window', 'delivery window',
                'creneau', 'creneau horaire', 'plage horaire',
                'franja horaria', 'horario',
            ]),
            new ColumnDefinition('after', ColumnType::Timeslot, ColumnGroup::Task, false, [
                'from', 'not before', 'start', 'earliest',
                'a partir de', 'pas avant', 'debut',
                'desde', 'no antes de',
            ]),
            new ColumnDefinition('before', ColumnType::Timeslot, ColumnGroup::Task, false, [
                'until', 'not after', 'end', 'latest', 'deadline',
                'jusqu a', 'pas apres', 'fin', 'au plus tard',
                'hasta', 'no despues de',
            ]),
            new ColumnDefinition('comments', ColumnType::Text, ColumnGroup::Task, false, [
                'comment', 'note', 'notes', 'instructions',
                'commentaire', 'commentaires', 'remarques',
                'comentarios', 'nota', 'instrucciones',
            ]),
            new ColumnDefinition('tags', ColumnType::Tags, ColumnGroup::Task, false, [
                'tag', 'labels',
                'etiquette', 'etiquettes',
                'etiqueta', 'etiquetas',
            ]),
            new ColumnDefinition('ref', ColumnType::Text, ColumnGroup::Task, false, [
                'reference', 'order', 'order number', 'order reference', 'external reference',
                'commande', 'numero de commande', 'reference commande',
                'referencia', 'pedido', 'numero de pedido',
            ]),
            new ColumnDefinition('packages', ColumnType::Packages, ColumnGroup::Task, false, [
                'package', 'parcels', 'goods',
                'colis', 'paquets', 'marchandise',
                'paquete', 'paquetes', 'bultos',
            ]),
            new ColumnDefinition('weight', ColumnType::Number, ColumnGroup::Task, false, [
                'weight kg', 'total weight',
                'poids', 'poids kg', 'poids total',
                'peso', 'peso kg',
            ]),
            new ColumnDefinition('group', ColumnType::Text, ColumnGroup::Task, false, [
                'task group', 'batch', 'round',
                'groupe', 'groupe de taches', 'tournee',
                'grupo', 'ronda',
            ]),
            new ColumnDefinition('assign', ColumnType::Text, ColumnGroup::Task, false, [
                'assigned to', 'courier', 'rider', 'assignee',
                'assigne a', 'coursier', 'livreur', 'attribue a',
                'asignado a', 'mensajero', 'repartidor',
            ]),
        ];
    }

    /**
     * A row says where its task goes either as an address or as coordinates.
     */
    public function getRequiredColumnGroups(): array
    {
        return [['address', 'latlong']];
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
     * Reads the rows into tasks, reporting what is wrong with a row on that row rather
     * than throwing on the first one: a file of a thousand rows is worth fixing in one
     * go, not one row per import.
     *
     * Nothing is written here. The task groups and the assignments are returned
     * alongside each task for the caller to apply inside its transaction, so that a
     * cancelled or rolled back import leaves neither behind.
     */
    public function parseData(array $data, array $options = []): SpreadsheetParseResult
    {
        $parseResult = new SpreadsheetParseResult();

        $this->assignees = [];

        $options = array_merge(
            [
                'create_task_if_address_not_geocoded' => false,
                // An unrecognised "type" quietly became a dropoff, which files imported
                // through the API rely on: only the new path is strict about it
                'strict_type_column' => false,
                'on_row' => null,
            ],
            $options
        );

        $defaultDate = new \DateTime('now');
        if (isset($options['date'])) {
            $defaultDate = $options['date'] instanceof \DateTime ? $options['date'] : new \DateTime($options['date']);
        }

        // Every row would fail the same way, but only after geocoding, which takes hours
        // on a large file: fail the whole file right away instead
        $missingColumns = $this->findMissingColumns($data);
        if (!empty($missingColumns)) {
            $parseResult->addErrorToRow(0, $this->translator->trans('import.columns.one_of.missing', [
                '%columns%' => implode(', ', $missingColumns)
            ]));

            return $parseResult;
        }

        // Everything that can be checked without geocoding is, for the whole file, in a
        // few seconds: better to fix the file and import it again than to find out about
        // a faulty row once the whole file is geocoded
        $this->preflight($data, $parseResult, $defaultDate, $options);

        if ($parseResult->hasErrors()) {
            return $parseResult;
        }

        $totalRows = count($data);
        $handledRows = 0;

        $reportRow = function () use (&$handledRows, $totalRows, $parseResult, $options): void {
            $handledRows++;

            if (!is_null($options['on_row'])) {
                $options['on_row']($handledRows, $totalRows, count($parseResult->getErrors()));
            }
        };

        foreach ($data as $index => $record) {
            $rowNumber = $index + 1;

            try {
                $address = $this->parseAddress($record);
            } catch (\Exception $e) {
                $parseResult->addErrorToRow($rowNumber, $e->getMessage());
                $reportRow();
                continue;
            }

            if (is_null($address)) {
                $reportRow();
                continue;
            }

            [ $after, $before ] = self::parseTimeWindow($record, $defaultDate);

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

            if (isset($record['ref']) && !empty($record['ref'])) {
                $task->setRef($record['ref']);
            }

            if (isset($record['packages']) && !empty($record['packages'])) {
                $this->parseAndApplyPackages($task, $record['packages']);
            }

            if (isset($record['weight']) && is_numeric($record['weight'])) {
                $task->setWeight($this->applyWeight($record['weight']));
            }

            $groupName = isset($record['group']) && !empty(trim($record['group']))
                ? trim($record['group'])
                : null;

            // Applied by the caller, inside its transaction: appending a task to a courier's
            // list changes a list that is already in the database
            $assign = null;
            if (isset($record['assign']) && !empty(trim($record['assign']))) {
                $assignee = $this->resolveAssign(trim($record['assign']));
                $assign = [$assignee['user'], $assignee['date']];
            }

            $parseResult->addData($rowNumber, [
                'task' => $task,
                'groupName' => $groupName,
                'assign' => $assign,
            ]);

            $reportRow();
        }

        return $parseResult;
    }

    /**
     * The address of a row, from the address column or from the coordinates.
     *
     * @return ?Address null when the row has neither and the options allow it
     *
     * @throws \Exception when the address is there but can't be geocoded
     */
    private function parseAddress(array $record): ?Address
    {
        $address = null;

        // Using isset() in order to parse spreadsheet with both address and coordinates columns later
        $addressHeader = 'address';
        if (isset($record['address.streetAddress'])) {
            $addressHeader = 'address.streetAddress';
        }

        if (isset($record[$addressHeader]) && !empty($record[$addressHeader])) {
            if (!$address = $this->geocoder->geocode($record[$addressHeader])) {
                throw new \Exception($this->translator->trans('import.address.geocode.error', [
                    '%failed_address%' => $record[$addressHeader],
                ]));
            }
        }

        $latlngHeader = 'latlong';
        if (isset($record['address.latlng'])) {
            $latlngHeader = 'address.latlng';
        }

        if (isset($record[$latlngHeader]) && !empty($record[$latlngHeader])) {
            [ $latitude, $longitude ] = $this->parseCoordinates($record[$latlngHeader]);
            if (!$address = $this->geocoder->reverse($latitude, $longitude)) {
                throw new \Exception($this->translator->trans('import.address.reverse_geocode.error', [
                    '%coordinates%' => $record[$latlngHeader],
                ]));
            }
        }

        if (is_null($address)) {
            return null;
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

        return $address;
    }

    /**
     * Everything that can be told about the rows without geocoding them.
     *
     * Cheap, and it runs over the whole file: a dispatcher gets every faulty row at once
     * rather than one per import, and a file that can't be saved is never geocoded.
     */
    private function preflight(array $data, SpreadsheetParseResult $parseResult, \DateTime $defaultDate, array $options): void
    {
        $refs = [];

        foreach ($data as $index => $record) {
            $rowNumber = $index + 1;

            $errors = array_merge(
                $this->checkAddress($record, $options),
                $this->checkCoordinates($record),
                $this->checkTimeWindow($record, $defaultDate),
                $this->checkType($record, $options),
                $this->checkAssign($record),
                $this->checkTelephone($record),
                $this->checkPackages($record),
                $this->checkWeight($record),
                $this->checkRef($record, $refs, $rowNumber),
                $this->checkLength($record, 'address.name'),
                $this->checkLength($record, 'address.contactName'),
                $this->checkLength($record, 'ref'),
                $this->checkLength($record, 'group'),
            );

            foreach ($errors as $error) {
                $parseResult->addErrorToRow($rowNumber, $error);
            }
        }
    }

    /**
     * @return string[]
     */
    private function checkAddress(array $record, array $options): array
    {
        if ($options['create_task_if_address_not_geocoded']) {
            return [];
        }

        foreach (['address', 'address.streetAddress', 'latlong', 'address.latlng'] as $key) {
            if (isset($record[$key]) && !empty(trim((string) $record[$key]))) {
                return [];
            }
        }

        return [$this->translator->trans('import.address.missing')];
    }

    /**
     * Two numbers separated by a comma. Anything else used to be read as the middle of
     * the Atlantic: floatval('N/A') is 0.0, and a missing second value raised.
     *
     * @return string[]
     */
    private function checkCoordinates(array $record): array
    {
        $key = isset($record['address.latlng']) ? 'address.latlng' : 'latlong';

        if (!isset($record[$key]) || empty(trim((string) $record[$key]))) {
            return [];
        }

        try {
            $this->parseCoordinates($record[$key]);
        } catch (\InvalidArgumentException $e) {
            return [$this->translator->trans('import.coordinates.error', [
                '%key%' => $key,
                '%value%' => $record[$key],
            ])];
        }

        return [];
    }

    /**
     * @return array{0: float, 1: float}
     *
     * @throws \InvalidArgumentException
     */
    private function parseCoordinates(string $value): array
    {
        $parts = array_map('trim', explode(',', trim($value)));

        if (2 !== count($parts) || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a pair of coordinates', $value));
        }

        $latitude = (float) $parts[0];
        $longitude = (float) $parts[1];

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a pair of coordinates', $value));
        }

        return [$latitude, $longitude];
    }

    /**
     * @return string[]
     */
    private function checkTimeWindow(array $record, \DateTime $defaultDate): array
    {
        try {
            self::parseTimeWindow($record, $defaultDate);
        } catch (\Exception $e) {
            return [$e->getMessage()];
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function checkType(array $record, array $options): array
    {
        if (!$options['strict_type_column'] || !isset($record['type']) || empty(trim((string) $record['type']))) {
            return [];
        }

        if (in_array(strtoupper(trim($record['type'])), [Task::TYPE_PICKUP, Task::TYPE_DROPOFF], true)) {
            return [];
        }

        return [$this->translator->trans('import.type.error', [
            '%value%' => $record['type'],
            '%types%' => implode(', ', [strtolower(Task::TYPE_PICKUP), strtolower(Task::TYPE_DROPOFF)]),
        ])];
    }

    /**
     * The commonest reason a task import fails, and the cheapest to check: a username
     * nobody has, or somebody who isn't a courier.
     *
     * @return string[]
     */
    private function checkAssign(array $record): array
    {
        if (!isset($record['assign']) || empty(trim((string) $record['assign']))) {
            return [];
        }

        $assignee = $this->resolveAssign(trim($record['assign']));

        return isset($assignee['error']) ? [$assignee['error']] : [];
    }

    /**
     * Looked up once per distinct value: a file assigning a hundred rows to the same
     * courier is one query, and the pass that follows the preflight reuses its answers.
     *
     * @return array{user?: mixed, date?: \DateTime, error?: string}
     */
    private function resolveAssign(string $value): array
    {
        if (!array_key_exists($value, $this->assignees)) {
            try {
                [ $user, $date ] = $this->extractAssign($value);

                $this->assignees[$value] = ['user' => $user, 'date' => $date];
            } catch (\Exception $e) {
                $this->assignees[$value] = ['error' => $e->getMessage()];
            }
        }

        return $this->assignees[$value];
    }

    /**
     * @return string[]
     */
    private function checkTelephone(array $record): array
    {
        $key = 'address.telephone';

        if (!isset($record[$key]) || empty($record[$key])) {
            return [];
        }

        try {
            $this->phoneNumberUtil->parse($record[$key], strtoupper($this->countryCode));
        } catch (NumberParseException $e) {
            return [$this->translator->trans('import.telephone.error', [
                '%key%' => $key,
                '%value%' => $record[$key],
            ])];
        }

        return [];
    }

    /**
     * As read by ParsePackagesTrait: slug=quantity, separated by spaces. A malformed
     * value used to raise out of list(), failing the whole file with a PHP error.
     *
     * @return string[]
     */
    private function checkPackages(array $record): array
    {
        $key = 'packages';

        if (!isset($record[$key]) || empty($record[$key])) {
            return [];
        }

        foreach (explode(' ', trim($record[$key])) as $package) {
            if (1 !== preg_match('/^[^=]+=\d+$/', $package)) {
                return [$this->translator->trans('import.packages.error', [
                    '%key%' => $key,
                    '%value%' => $record[$key],
                ])];
            }
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function checkWeight(array $record): array
    {
        $key = 'weight';

        if (!isset($record[$key]) || empty(trim((string) $record[$key]))) {
            return [];
        }

        if (is_numeric($record[$key])) {
            return [];
        }

        return [$this->translator->trans('import.weight.error', [
            '%key%' => $key,
            '%value%' => $record[$key],
        ])];
    }

    /**
     * A task's reference is unique per organization, so a single collision fails the
     * flush, and with it the whole file.
     *
     * @param array<string,int> $refs the row each reference was first seen on
     *
     * @return string[]
     */
    private function checkRef(array $record, array &$refs, int $rowNumber): array
    {
        if (!isset($record['ref']) || empty(trim((string) $record['ref']))) {
            return [];
        }

        $ref = trim($record['ref']);

        if (array_key_exists($ref, $refs)) {
            return [$this->translator->trans('import.ref.duplicate', [
                '%value%' => $ref,
                '%row%' => $refs[$ref],
            ])];
        }

        $refs[$ref] = $rowNumber;

        return [];
    }

    /**
     * A longer value doesn't fit its column, which fails the whole import when it's saved
     *
     * @return string[]
     */
    private function checkLength(array $record, string $key): array
    {
        if (!isset($record[$key]) || mb_strlen((string) $record[$key]) <= self::MAX_TEXT_LENGTH) {
            return [];
        }

        return [$this->translator->trans('import.value.too_long', [
            '%key%' => $key,
            '%max%' => self::MAX_TEXT_LENGTH,
        ])];
    }

    /**
     * A file has to say where each task goes, as an address or as coordinates.
     *
     * @param array<int,array<string,mixed>> $data
     * @return string[] the keys of the group that is missing, if any
     */
    private function findMissingColumns(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        // All the rows have the same keys, taken from the header
        $columns = array_keys(reset($data));

        foreach ($this->getRequiredColumnGroups() as $group) {
            // The legacy spellings count: a file whose header says address.streetAddress
            // is read by its header names, and has no mapping to go by
            $alternatives = array_merge($group, ['address.streetAddress', 'address.latlng']);

            if (empty(array_intersect($alternatives, $columns))) {
                return $group;
            }
        }

        return [];
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

    /**
     * The column is in kilograms, a task's weight is in grams
     */
    private function applyWeight($weight): int
    {
        $mass = new Mass($weight, 'kg');

        return (int) $mass->toUnit('g');
    }
}
