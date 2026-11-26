<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Task;
use AppBundle\Exception\DateTimeParseException;
use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use AppBundle\Spreadsheet\Column\ColumnDefinition;
use AppBundle\Spreadsheet\Column\ColumnGroup;
use AppBundle\Spreadsheet\Column\ColumnType;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeliverySpreadsheetParser extends AbstractSpreadsheetParser
{
    use ParsePackagesTrait;
    use ParseMetadataTrait;

    private $defaultCoordinates;

    public function __construct(
        private Geocoder $geocoder,
        private PhoneNumberUtil $phoneNumberUtil,
        private string $countryCode,
        private EntityManagerInterface $entityManager,
        private SlugifyInterface $slugify,
        private TranslatorInterface $translator,
        private SettingsManager $settingsManager
    ) {}

    /**
     * A file with a missing column, or a row failing one of the checks that don't need geocoding,
     * is rejected as a whole, before any address is geocoded.
     *
     * Options:
     * - create_task_if_address_not_geocoded: keep a row whose address can't be geocoded
     * - on_row: called after each row with the number of rows handled so far,
     *   the number of rows in the file and the number of rows with errors so far.
     *   Whatever it throws stops the parsing.
     *
     * @inheritdoc
     */
    public function parseData(array $data, array $options = []): SpreadsheetParseResult
    {
        $this->setup();
        $parseResult = new SpreadsheetParseResult();

        $options = array_merge(
            [
                'create_task_if_address_not_geocoded' => false,
                'on_row' => null,
            ],
            $options
        );

        // Every row would fail on the missing column, but only after geocoding its addresses,
        // which takes hours on a large file: fail the whole file right away instead
        $missingColumns = $this->findMissingColumns($data);
        if (!empty($missingColumns)) {
            $parseResult->addErrorToRow(0, $this->translator->trans('import.columns.missing', [
                '%columns%' => implode(', ', $missingColumns)
            ]));

            return $parseResult;
        }

        // Same idea for the values: everything that can be checked without geocoding is,
        // for the whole file, in a few seconds
        $this->preflight($data, $parseResult, $options);

        // The deliveries are saved all at once, or not at all: better to fix the file and import
        // it again right away than to find out about a single faulty row once the whole file is geocoded
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

        foreach ($data as $index=>$record) {
            $rowNumber = $index + 1;

            $delivery = new Delivery();

            if (!$record['pickup.address']) {
                $pickupAddress = $this->handleFaultyAddress($parseResult, $rowNumber, 'pickup.address', 'pickup', $options);
            } else {
                try {
                    $pickupAddress = $this->geocoder->geocode($record['pickup.address']);
                } catch (Exception $e) {
                    $pickupAddress = $this->handleFaultyAddress($parseResult, $rowNumber, 'pickup.address', $record['pickup.address'], $options);
                }

                if (!$pickupAddress) {
                    $pickupAddress = $this->handleFaultyAddress($parseResult, $rowNumber, 'pickup.address', $record['pickup.address'], $options);
                }
            }

            // TODO: Report an ADDRESS_REVIEW_NEEDED incident instead of the deprecated tag,
            // as AppBundle\Transporter\ImportFromPoint does (here and for the dropoff below).
            if ($pickupAddress && $pickupAddress->getGeo()->isEqualTo($this->defaultCoordinates)) {
                $delivery->getPickup()->addTags(Tag::ADDRESS_NEED_REVIEW_TAG);
                //TODO: Trigger a incident.
            }

            $delivery->getPickup()->setAddress($pickupAddress);

            if (!$record['dropoff.address']) {
                $dropoffAddress = $this->handleFaultyAddress($parseResult, $rowNumber, 'dropoff.address', 'dropoff', $options);
            } else {
                try {
                    $dropoffAddress = $this->geocoder->geocode($record['dropoff.address']);
                } catch (Exception $e) {
                    $dropoffAddress = $this->handleFaultyAddress($parseResult, $rowNumber, 'dropoff.address', $record['dropoff.address'], $options);
                }

                if (!$dropoffAddress) {
                    $dropoffAddress = $this->handleFaultyAddress($parseResult, $rowNumber, 'dropoff.address', $record['dropoff.address'], $options);
                }
            }

            if ($dropoffAddress && $dropoffAddress->getGeo()->isEqualTo($this->defaultCoordinates)) {
                $delivery->getDropoff()->addTags(Tag::ADDRESS_NEED_REVIEW_TAG);
                //TODO: Trigger a incident.
            }

            $delivery->getDropoff()->setAddress($dropoffAddress);

            try {
                [ $pickupAfter, $pickupBefore ] = $this->parseTimeRange($record, 'pickup.timeslot');
                [ $dropoffAfter, $dropoffBefore ] = $this->parseTimeRange($record, 'dropoff.timeslot');

                $delivery->getPickup()->setDoneAfter($pickupAfter);
                $delivery->getPickup()->setDoneBefore($pickupBefore);
                $delivery->getDropoff()->setDoneAfter($dropoffAfter);
                $delivery->getDropoff()->setDoneBefore($dropoffBefore);
            } catch(DateTimeParseException $e) {
                $parseResult->addErrorToRow($rowNumber, $e->getMessage());
            }

            try {
                $this->enhanceTask($delivery->getPickup(), 'pickup', $record);
                $this->enhanceTask($delivery->getDropoff(), 'dropoff', $record);
            } catch(NumberParseException $e) {
                $parseResult->addErrorToRow($rowNumber, $e->getMessage());
            }

            if (isset($record['pickup.metadata']) && !empty($record['pickup.metadata'])) {
                try {
                    $this->parseAndApplyMetadata($delivery->getPickup(), $record['pickup.metadata']);
                } catch (Exception $e) {
                    $parseResult->addErrorToRow($rowNumber, 'Unable to parse pickup metadata');
                }
            }

            if (isset($record['dropoff.metadata']) && !empty($record['dropoff.metadata'])) {
                try {
                    $this->parseAndApplyMetadata($delivery->getDropoff(), $record['dropoff.metadata']);
                } catch (Exception $e) {
                    $parseResult->addErrorToRow($rowNumber, 'Unable to parse dropoff metadata');
                }
            }

            if (isset($record['weight']) && is_numeric($record['weight'])) {
                $delivery->setWeight(floatval($record['weight']) * 1000);
            }

            if (isset($record['external_reference']) && !empty(trim($record['external_reference']))) {
                $delivery->setExternalReference(trim($record['external_reference']));
            }

            if (isset($record['dropoff.packages']) && !empty($record['dropoff.packages'])) {
                $this->parseAndApplyPackages($delivery->getDropoff(), trim($record['dropoff.packages']));
            }

            if (isset($record['pickup.tags']) && !empty($record['pickup.tags'])) {
                $this->applyTags($delivery->getPickup(), $record['pickup.tags']);
            }

            if (isset($record['dropoff.tags']) && !empty($record['dropoff.tags'])) {
                $this->applyTags($delivery->getDropoff(), $record['dropoff.tags']);
            }

            $tourName = isset($record['tour.name']) && !empty($record['tour.name']) ? $record['tour.name'] : null;

            if (!$parseResult->rowHasErrors($rowNumber)) {
                $parseResult->addData(
                    $rowNumber,
                    ['delivery' => $delivery, 'tourName' => $tourName]
                );
            }

            $reportRow();
        }
        return $parseResult;
    }

    /**
     * The checks that don't need geocoding, made on every row before any address is geocoded:
     * the values the full pass would reject, and those the database would, failing the whole import.
     *
     * @param array<int,array<string,mixed>> $data
     */
    private function preflight(array $data, SpreadsheetParseResult $parseResult, array $options): void
    {
        foreach ($data as $index => $record) {
            $rowNumber = $index + 1;

            // With the option set, a row without an address is kept, with a placeholder
            if (!$options['create_task_if_address_not_geocoded']) {
                if (!$record['pickup.address']) {
                    $this->handleFaultyAddress($parseResult, $rowNumber, 'pickup.address', 'pickup', $options);
                }
                if (!$record['dropoff.address']) {
                    $this->handleFaultyAddress($parseResult, $rowNumber, 'dropoff.address', 'dropoff', $options);
                }
            }

            $errors = array_merge(
                $this->checkTimeSlots($record),
                $this->checkTelephone($record, 'pickup.address.telephone'),
                $this->checkTelephone($record, 'dropoff.address.telephone'),
                $this->checkMetadata($record, 'pickup.metadata'),
                $this->checkMetadata($record, 'dropoff.metadata'),
                $this->checkPackages($record, 'dropoff.packages'),
                $this->checkLength($record, 'pickup.address.name'),
                $this->checkLength($record, 'dropoff.address.name'),
                $this->checkLength($record, 'tour.name'),
            );

            foreach ($errors as $error) {
                $parseResult->addErrorToRow($rowNumber, $error);
            }
        }
    }

    /**
     * @param array<string,mixed> $record
     * @return string[]
     */
    private function checkTimeSlots(array $record): array
    {
        $errors = [];
        $timeSlots = [];

        foreach (['pickup.timeslot', 'dropoff.timeslot'] as $key) {
            try {
                $timeSlots[$key] = $this->parseTimeRange($record, $key);
            } catch (DateTimeParseException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            return $errors;
        }

        // Otherwise only found out by the validator, when saving
        [, $pickupBefore] = $timeSlots['pickup.timeslot'];
        [, $dropoffBefore] = $timeSlots['dropoff.timeslot'];

        if ($pickupBefore > $dropoffBefore) {
            return [$this->translator->trans('delivery.tasks.pickupAfterDropoff', [], 'validators')];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $record
     * @return string[]
     */
    private function checkTelephone(array $record, string $key): array
    {
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
     * As read by ParseMetadataTrait: key=value, separated by spaces
     *
     * @param array<string,mixed> $record
     * @return string[]
     */
    private function checkMetadata(array $record, string $key): array
    {
        if (!isset($record[$key]) || empty($record[$key])) {
            return [];
        }

        foreach (explode(' ', trim($record[$key])) as $metadata) {
            if (!str_contains($metadata, '=')) {
                return [$this->translator->trans('import.metadata.error', [
                    '%key%' => $key,
                    '%value%' => $record[$key],
                ])];
            }
        }

        return [];
    }

    /**
     * As read by ParsePackagesTrait: slug=quantity, separated by spaces.
     * An unknown package is left out of the delivery, as before.
     *
     * @param array<string,mixed> $record
     * @return string[]
     */
    private function checkPackages(array $record, string $key): array
    {
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
     * A longer value doesn't fit its column, which fails the whole import when it's saved
     *
     * @param array<string,mixed> $record
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
     * @param array<int,array<string,mixed>> $data
     * @return string[]
     */
    private function findMissingColumns(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        // All the rows have the same keys, taken from the header
        $columns = array_keys(reset($data));

        return array_values(array_diff($this->getRequiredColumnKeys(), $columns));
    }

    private function setup(): void
    {
      $pos = explode(',', $this->settingsManager->get('latlng') ?? '');
        if (count($pos) !== 2) {
            $pos = [0, 0];
        }
        $this->defaultCoordinates = new GeoCoordinates($pos[0], $pos[1]);
    }

    private function handleFaultyAddress(SpreadsheetParseResult $parseResult, int $rowNumber, string $recordKey, string $erroredRecordString, array $options) {
        if ($options['create_task_if_address_not_geocoded']) {
            $address = new Address();
            $address->setGeo($this->defaultCoordinates);
            $address->setStreetAddress('INVALID ADDRESS');
            return $address;
        } else {
            $translatedError = $this->translator->trans('import.address.geocode.error', [
                '%failed_address%' => $erroredRecordString
            ]);
            $parseResult->addErrorToRow($rowNumber,
                sprintf('%s: %s', $recordKey, $translatedError)
            );
            return null;
        }
    }

    private function parseTimeRange($data, $key)
    {
        $timeSlotAsText = $data[$key];

        try {
            return DateParser::parseTimeslot($timeSlotAsText);
        } catch (\Exception $e) {
            throw new DateTimeParseException(
                $this->translator->trans('import.time.range.error', [
                    '%key%' => $key,
                    '%value%' => $timeSlotAsText
                ])
            );
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

        if (null !== $task->getAddress()) {
            if (isset($record[$addressNameColumn]) && !empty($record[$addressNameColumn])) {
                $task->getAddress()->setName((string) $record[$addressNameColumn]);
            }

            if (isset($record[$addressDescriptionColumn]) && !empty($record[$addressDescriptionColumn])) {
                $task->getAddress()->setDescription($record[$addressDescriptionColumn]);
            }

            if (isset($record[$addressTelephoneColumn]) && !empty($record[$addressTelephoneColumn])) {
                /* @throws NumberParseException */
                $phoneNumber = $this->phoneNumberUtil->parse($record[$addressTelephoneColumn], strtoupper($this->countryCode));
                $task->getAddress()->setTelephone($phoneNumber);
            }
        }

        if (isset($record[$commentsColumn]) && !empty($record[$commentsColumn])) {
            $task->setComments($record[$commentsColumn]);
        }
    }

    private function applyTags(TaggableInterface $task, $tagsAsString)
    {
        $tagsAsString = preg_replace("/[[:blank:]]+/", " ", trim($tagsAsString));

        if (!empty($tagsAsString)) {
            $slugs = explode(' ', $tagsAsString);
            $tags = array_map([$this->slugify, 'slugify'], $slugs);
            $task->addTags($tags);
        }
    }

    /**
     * Every column a delivery can be imported from, in the order the example file has them.
     *
     * The aliases are what a file that doesn't use our own names tends to call the column.
     * They say which of pickup and dropoff a column is about where they can: a timeslot
     * column named "créneau" alone is deliberately left for the user to assign, as putting
     * a dropoff time on a pickup is both easy to do and hard to notice.
     *
     * @inheritdoc
     */
    public function getColumns(): array
    {
        return [
            new ColumnDefinition('pickup.address', ColumnType::Address, ColumnGroup::Pickup, true, [
                'pickup address', 'pickup street address', 'collection address', 'sender address',
                'adresse enlevement', 'adresse de retrait', 'adresse expediteur', 'adresse depart',
                'direccion recogida', 'direccion de origen',
            ]),
            new ColumnDefinition('pickup.address.name', ColumnType::Text, ColumnGroup::Pickup, false, [
                'pickup name', 'pickup contact', 'sender name', 'sender',
                'nom enlevement', 'nom expediteur', 'expediteur',
                'nombre recogida', 'remitente',
            ]),
            new ColumnDefinition('pickup.address.description', ColumnType::Text, ColumnGroup::Pickup, false, [
                'pickup address description', 'pickup details', 'pickup instructions',
                'complement adresse enlevement', 'complement enlevement', 'details enlevement',
                'detalles recogida',
            ]),
            new ColumnDefinition('pickup.address.telephone', ColumnType::Phone, ColumnGroup::Pickup, false, [
                'pickup telephone', 'pickup phone', 'pickup phone number', 'sender phone',
                'telephone enlevement', 'tel enlevement', 'telephone expediteur',
                'telefono recogida',
            ]),
            new ColumnDefinition('pickup.comments', ColumnType::Text, ColumnGroup::Pickup, false, [
                'pickup comments', 'pickup note', 'pickup notes',
                'commentaire enlevement', 'commentaires enlevement', 'note enlevement',
                'comentarios recogida',
            ]),
            new ColumnDefinition('pickup.timeslot', ColumnType::Timeslot, ColumnGroup::Pickup, true, [
                'pickup timeslot', 'pickup time slot', 'pickup time', 'pickup date', 'pickup window',
                'creneau enlevement', 'creneau de retrait', 'horaire enlevement', 'date enlevement',
                'franja recogida', 'horario recogida',
            ]),
            new ColumnDefinition('pickup.tags', ColumnType::Tags, ColumnGroup::Pickup, false, [
                'pickup tags', 'pickup labels',
                'etiquettes enlevement', 'tags enlevement',
                'etiquetas recogida',
            ]),
            new ColumnDefinition('pickup.metadata', ColumnType::Metadata, ColumnGroup::Pickup, false, [
                'pickup metadata',
                'metadonnees enlevement',
                'metadatos recogida',
            ]),
            new ColumnDefinition('dropoff.address', ColumnType::Address, ColumnGroup::Dropoff, true, [
                'dropoff address', 'drop off address', 'delivery address', 'recipient address',
                'shipping address', 'destination address',
                'adresse livraison', 'adresse de livraison', 'adresse destinataire', 'adresse arrivee',
                'direccion entrega', 'direccion de destino',
            ]),
            new ColumnDefinition('dropoff.address.name', ColumnType::Text, ColumnGroup::Dropoff, false, [
                'dropoff name', 'dropoff contact', 'recipient name', 'recipient', 'customer name',
                'nom livraison', 'nom destinataire', 'destinataire', 'nom client',
                'nombre entrega', 'destinatario',
            ]),
            new ColumnDefinition('dropoff.address.description', ColumnType::Text, ColumnGroup::Dropoff, false, [
                'dropoff address description', 'dropoff details', 'dropoff instructions',
                'delivery instructions',
                'complement adresse livraison', 'complement livraison', 'details livraison',
                'detalles entrega',
            ]),
            new ColumnDefinition('dropoff.address.telephone', ColumnType::Phone, ColumnGroup::Dropoff, false, [
                'dropoff telephone', 'dropoff phone', 'dropoff phone number', 'recipient phone',
                'customer phone',
                'telephone livraison', 'tel livraison', 'telephone destinataire', 'telephone client',
                'telefono entrega',
            ]),
            new ColumnDefinition('dropoff.comments', ColumnType::Text, ColumnGroup::Dropoff, false, [
                'dropoff comments', 'dropoff note', 'dropoff notes', 'delivery comments',
                'commentaire livraison', 'commentaires livraison', 'note livraison',
                'comentarios entrega',
            ]),
            new ColumnDefinition('dropoff.timeslot', ColumnType::Timeslot, ColumnGroup::Dropoff, true, [
                'dropoff timeslot', 'dropoff time slot', 'dropoff time', 'dropoff date',
                'delivery timeslot', 'delivery time', 'delivery date', 'dropoff window',
                'creneau livraison', 'creneau de livraison', 'horaire livraison', 'date livraison',
                'franja entrega', 'horario entrega',
            ]),
            new ColumnDefinition('dropoff.packages', ColumnType::Packages, ColumnGroup::Dropoff, false, [
                'packages', 'dropoff packages', 'parcels', 'colis',
                'paquets', 'paquetes', 'bultos',
            ]),
            new ColumnDefinition('dropoff.tags', ColumnType::Tags, ColumnGroup::Dropoff, false, [
                'dropoff tags', 'dropoff labels',
                'etiquettes livraison', 'tags livraison',
                'etiquetas entrega',
            ]),
            new ColumnDefinition('dropoff.metadata', ColumnType::Metadata, ColumnGroup::Dropoff, false, [
                'dropoff metadata',
                'metadonnees livraison',
                'metadatos entrega',
            ]),
            new ColumnDefinition('weight', ColumnType::Number, ColumnGroup::Delivery, false, [
                'weight', 'weight kg', 'total weight',
                'poids', 'poids kg', 'poids total',
                'peso',
            ]),
            new ColumnDefinition('tour.name', ColumnType::Text, ColumnGroup::Delivery, false, [
                'tour', 'tour name', 'round', 'round name',
                'tournee', 'nom tournee', 'nom de la tournee',
                'ruta', 'nombre ruta',
            ]),
            new ColumnDefinition('external_reference', ColumnType::Text, ColumnGroup::Delivery, false, [
                'external reference', 'reference', 'order reference', 'order id', 'order number',
                'reference externe', 'reference commande', 'numero de commande', 'num commande',
                'referencia', 'referencia externa', 'numero de pedido',
            ]),
        ];
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
                'pickup.tags' => 'warn heavy',
                'pickup.metadata' => 'external_system_id=10 my_meta=value',
                'dropoff.address' => '58 av parmentier paris',
                'dropoff.address.name' => 'Awesome business',
                'dropoff.address.description' => 'Buzzer AB12',
                'dropoff.address.telephone' => '+33612345678',
                'dropoff.comments' => '',
                'dropoff.timeslot' => '2019-12-12 12:00 - 2019-12-12 13:00',
                'dropoff.packages' => 'small-box=1 big-box=2',
                'dropoff.tags' => 'warn heavy',
                'dropoff.metadata' => 'external_system_id=10',
                'weight' => '5.5',
                'tour.name' => 'my tour name',
                'external_reference' => 'ORDER-1234',
            ],
            [
                'pickup.address' => '24 rue de rivoli paris',
                'pickup.address.name' => 'Awesome business',
                'pickup.address.description' => '',
                'pickup.address.telephone' => '+33612345678',
                'pickup.comments' => 'Fragile',
                'pickup.timeslot' => '2019-12-12 10:00 - 2019-12-12 11:00',
                'pickup.tags' => 'warn',
                'dropoff.address' => '34 bd de magenta paris',
                'dropoff.address.name' => 'Awesome business',
                'dropoff.address.description' => 'Buzzer AB12',
                'dropoff.address.telephone' => '+33612345678',
                'dropoff.comments' => '',
                'dropoff.timeslot' => '2019-12-12 12:00 - 2019-12-12 13:00',
                'dropoff.packages' => 'small-box=1 big-box=2',
                'dropoff.tags' => 'warn',
                'weight' => '8.0',
                'tour.name' => 'another tour name'
            ],
        ];
    }
}
