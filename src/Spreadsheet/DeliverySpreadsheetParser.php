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

    private $defaultCoordinates;

    public function __construct(
        private Geocoder $geocoder,
        private PhoneNumberUtil $phoneNumberUtil,
        private string $countryCode,
        private EntityManagerInterface $entityManager,
        private SlugifyInterface $slugify,
        private TranslatorInterface $translator,
        private SettingsManager $settingsManager
    )
    {  }

    /**
     * @inheritdoc
     */
    public function parseData(array $data, array $options = []): SpreadsheetParseResult
    {
        $this->setup();
        $parseResult = new SpreadsheetParseResult();

        $options = array_merge(
            ['create_task_if_address_not_geocoded' => false],
            $options
        );

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

            if (isset($record['dropoff.packages']) && !empty($record['dropoff.packages'])) {
                $this->parseAndApplyPackages($delivery->getDropoff(), $record['dropoff.packages']);
            }

            if (isset($record['pickup.tags']) && !empty($record['pickup.tags'])) {
                $this->applyTags($delivery->getPickup(), $record['pickup.tags']);
            }

            if (isset($record['dropoff.tags']) && !empty($record['dropoff.tags'])) {
                $this->applyTags($delivery->getDropoff(), $record['dropoff.tags']);
            }

            if (!$parseResult->rowHasErrors($rowNumber)) {
                $parseResult->addData($rowNumber, $delivery);
            }

        }
        return $parseResult;
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
                'weight' => '5.5'
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
                'weight' => '8.0'
            ],
        ];
    }
}
