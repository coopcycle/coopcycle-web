<?php

namespace AppBundle\Transporter;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use Transporter\DTO\CommunicationMean;
use Transporter\DTO\Mesurement;
use Transporter\DTO\NameAndAddress;
use Transporter\DTO\Point;
use Transporter\Enum\CommunicationMeanType;
use Transporter\Enum\NameAndAddressType;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;

class ImportFromPoint {

    private GeoCoordinates $defaultCoordinates;

    public function __construct(
        private Geocoder $geocoder,
        private PhoneNumberUtil $phoneUtil
    ) {
        $this->defaultCoordinates = new GeoCoordinates(0,0);
    }


    public function import(
        Point $point,
        ?EDIFACTMessage $edi = null
    ): Task
    {
        $nad = $point->getNamesAndAddresses(NameAndAddressType::RECIPIENT);
        if (count($nad) !== 1) {
            throw new TransporterException(sprintf(
                "Cannot handle multiple recipients: %d",
                count($nad)
            ));
        }
        $nad = array_shift($nad);
        $address = $this->addressFromNAD($nad);

        $imported_from = sprintf(
            "%s\n%s\n\n%s\n",
            $nad->getAddressLabel(),
            $nad->getAddress(),
            $nad->getContactName()
        );
        $imported_from .= collect($nad->getCommunicationMeans())
        ->map(fn(CommunicationMean $c) => $c->getType()->name . ': ' . $c->getValue())
        ->join("\n");

        $task = new Task();
        $task->setAddress($address);
        $task->setComments($point->getComments());
        $task->setMetadata('imported_from', $imported_from);
        if (!is_null($edi)) {
            $task->addEdifactMessage($edi);
        }

        if ($address->getGeo()->isEqualTo($this->defaultCoordinates)) {
            $task->setTags('review-needed');
            //TODO: Trigger a incident.
        }

        $weight = array_sum(array_map(
            fn(Mesurement $p) => $p->getQuantity(),
            $point->getMesurements()
        ));
        $task->setWeight($weight * 1000);


        //TODO: Maybe add package codes


        return $task;
    }

    public function buildPickupTask(
        Address $address,
        ?EDIFACTMessage $edi = null
    ): Task
    {
        $task = new Task();
        $task->setType(Task::TYPE_PICKUP);
        $task->setAddress($address);
        if (!is_null($edi)) {
            $task->addEdifactMessage($edi);
        }

        return $task;
    }

    public function setDefaultCoordinates(
        GeoCoordinates $defaultCoordinates
    ): void
    {
        $this->defaultCoordinates = $defaultCoordinates;
    }

    private function addressFromNAD(
        NameAndAddress $nad
    ): Address
    {
        $address = $this->geocoder->geocode($nad->getAddress());

        if (
            is_null($address) ||
            !$this->isInRange($this->defaultCoordinates, $address->getGeo())
        ) {
            $address = new Address();
            $address->setGeo($this->defaultCoordinates);
            $address->setStreetAddress('INVALID ADDRESS');
        }
        $address->setCompany($nad->getAddressLabel());
        $address->setName($nad->getAddressLabel());
        $address->setContactName($nad->getContactName());
        $address->setTelephone($this->PhoneNumberFromPhone($nad->getCommunicationMeans()));
        return $address;
    }

    /**
     * @param array<int,mixed> $communicationMeans
     */
    private function PhoneNumberFromPhone(array $communicationMeans): ?PhoneNumber
    {
        $phone = collect($communicationMeans)
        ->filter(fn(CommunicationMean $c) => $c->getType() === CommunicationMeanType::PHONE)
        ->map(fn(CommunicationMean $c) => $c->getValue())
        ->first();

        if (!is_null($phone)) {
            try {
                //TODO: Handle country code
                $phone = $this->phoneUtil->parse($phone, 'FR');
            } catch (\Exception $e) {
                return null;
            }
        }

        return $phone;

    }

    private function isInRange(
        GeoCoordinates $from,
        GeoCoordinates $to,
        int $distance = 50000
    ): bool
    {
        $p1 = deg2rad($from->getLatitude());
        $p2 = deg2rad($to->getLatitude());
        $dp = deg2rad($to->getLatitude() - $from->getLatitude());
        $dl = deg2rad($to->getLongitude() - $from->getLongitude());
        $a = (sin($dp/2) * sin($dp/2)) + (cos($p1) * cos($p2) * sin($dl/2) * sin($dl/2));
        $c = 2 * atan2(sqrt($a),sqrt(1-$a));
        return (6371008 * $c) <= $distance;

    }

}
