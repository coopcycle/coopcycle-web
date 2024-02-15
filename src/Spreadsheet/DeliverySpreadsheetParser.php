<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Exception\DateTimeParseException;
use AppBundle\Service\Geocoder;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeliverySpreadsheetParser extends AbstractSpreadsheetParser
{
    use ParsePackagesTrait;

    private $geocoder;
    private $phoneNumberUtil;
    private $countryCode;
    private $entityManager;
    private $slugify;
    private $translator;

    public function __construct(Geocoder $geocoder, PhoneNumberUtil $phoneNumberUtil, string $countryCode,
        EntityManagerInterface $entityManager, SlugifyInterface $slugify, TranslatorInterface $translator)
    {
        $this->geocoder = $geocoder;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->countryCode = $countryCode;
        $this->entityManager = $entityManager;
        $this->slugify = $slugify;
        $this->translator = $translator;
    }

    /**
     * @inheritdoc
     */
    public function parseData(array $data, array $options = []): SpreadsheetParseResult
    {
        $parseResult = new SpreadsheetParseResult();

        foreach ($data as $index=>$record) {
            $rowNumber = $index + 1;

            if (!$pickupAddress = $this->geocoder->geocode($record['pickup.address'])) {
                $translatedError = $this->translator->trans('import.address.geocode.error', [
                    '%failed_address%' => $record['pickup.address']
                ]);
                $parseResult->addErrorToRow($rowNumber,
                    sprintf('pickup.address: %s', $translatedError)
                );
            }

            if (!$dropoffAddress = $this->geocoder->geocode($record['dropoff.address'])) {
                $translatedError = $this->translator->trans('import.address.geocode.error', [
                    '%failed_address%' => $record['dropoff.address']
                ]);
                $parseResult->addErrorToRow($rowNumber,
                    sprintf('dropoff.address: %s', $translatedError)
                );
            }

            $delivery = new Delivery();

            $delivery->getPickup()->setAddress($pickupAddress);

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
            $task->setTags($tags);
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
                'dropoff.address' => '58 av parmentier paris',
                'dropoff.address.name' => 'Awesome business',
                'dropoff.address.description' => 'Buzzer AB12',
                'dropoff.address.telephone' => '+33612345678',
                'dropoff.comments' => '',
                'dropoff.timeslot' => '2019-12-12 12:00 - 2019-12-12 13:00',
                'dropoff.packages' => 'small-box=1 big-box=2',
                'dropoff.tags' => 'warn heavy',
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
