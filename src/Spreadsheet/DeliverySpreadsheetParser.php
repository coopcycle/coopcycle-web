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

    /**
     * A row without one of these columns can't be turned into a delivery
     */
    const REQUIRED_COLUMNS = [
        'pickup.address',
        'pickup.timeslot',
        'dropoff.address',
        'dropoff.timeslot',
    ];

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

        // Same idea for the values: an empty address or an unreadable time slot is found
        // in a few seconds for the whole file, rather than row by row after geocoding
        $this->checkMandatoryValues($data, $parseResult, $options);

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

            // Already failed the first pass: nothing to gain from geocoding its addresses
            if ($parseResult->rowHasErrors($rowNumber)) {
                $reportRow();
                continue;
            }

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
                $this->parseAndApplyPackages($delivery->getDropoff(), $record['dropoff.packages']);
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
     * The checks that don't need geocoding, made on every row before any address is
     * geocoded. They give the same errors as the full pass, which skips the rows failing them.
     *
     * @param array<int,array<string,mixed>> $data
     */
    private function checkMandatoryValues(array $data, SpreadsheetParseResult $parseResult, array $options): void
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

            foreach (['pickup.timeslot', 'dropoff.timeslot'] as $key) {
                try {
                    $this->parseTimeRange($record, $key);
                } catch (DateTimeParseException $e) {
                    $parseResult->addErrorToRow($rowNumber, $e->getMessage());
                }
            }
        }
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

        return array_values(array_diff(self::REQUIRED_COLUMNS, $columns));
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
