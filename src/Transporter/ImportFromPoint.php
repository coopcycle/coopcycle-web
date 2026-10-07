<?php

namespace AppBundle\Transporter;

use AppBundle\Action\Incident\CreateIncident;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Transporter\DTO\CommunicationMean;
use Transporter\DTO\Mesurement;
use Transporter\DTO\NameAndAddress;
use Transporter\DTO\Package as TransporterPackage;
use Transporter\DTO\Point;
use Transporter\Enum\CommunicationMeanType;
use Transporter\Enum\INOVERTMessageType;
use Transporter\Enum\NameAndAddressType;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;

class ImportFromPoint {

    private GeoCoordinates $defaultCoordinates;

    /**
     * Maps a Transporter\Enum\ProductType case name (e.g. "PACKAGE",
     * "HANDLING_UNIT") to the AppBundle\Entity\Package it corresponds to.
     *
     * @var array<string,Package>
     */
    private array $packageMapping = [];

    /**
     * Address problems found by import(), reported as incidents
     * by reportAddressIssue() once the task is persisted.
     *
     * @var \WeakMap<Task,array{0:string,1:array<string,string>}>
     */
    private \WeakMap $addressIssues;

    public function __construct(
        private Geocoder $geocoder,
        private PhoneNumberUtil $phoneUtil,
        private LoggerInterface $transporterLogger,
        private CreateIncident $createIncident,
        private TranslatorInterface $translator
    ) {
        $this->defaultCoordinates = new GeoCoordinates(0,0);
        $this->addressIssues = new \WeakMap();
    }


    public function import(
        Point $point,
        ?EDIFACTMessage $edi = null
    ): Task
    {
        $nad = $point->getNamesAndAddresses(NameAndAddressType::RECIPIENT);
        if (count($nad) !== 1) {
            $message = sprintf(
                "Cannot handle multiple recipients: %d",
                count($nad)
            );
            $this->transporterLogger->critical($message);
            throw new TransporterException($message);
        }
        $nad = array_shift($nad);
        [$address, $addressIssue] = $this->addressFromNAD($nad);

        $imported_from = sprintf(
            "%s\n%s\n\n%s\n",
            $nad->getAddressLabel(),
            $nad->getAddress(),
            $nad->getContactName()
        );
        $imported_from .= collect($nad->getCommunicationMeans())
        ->map(fn(CommunicationMean $c) => $c->getType()->name . ': ' . $c->getValue())
        ->join("\n");

        $taskType = match ($point->getType()) {
            INOVERTMessageType::SCONTR => Task::TYPE_DROPOFF,
            INOVERTMessageType::PICKUP => Task::TYPE_PICKUP,
            INOVERTMessageType::DISPOR => Task::TYPE_DROPOFF,
        };

        $task = new Task();
        $task->setType($taskType);
        $task->setAddress($address);
        $task->setComments($point->getComments());
        $task->setMetadata('imported_from', $imported_from);
        $task->setMetadata('barcode', $point->getId());
        if (!is_null($edi)) {
            $task->addEdifactMessage($edi);
        }

        if (null !== $addressIssue) {
            $this->addressIssues[$task] = $addressIssue;
        }

        $weight = array_sum(array_map(
            fn(Mesurement $p) => $p->getQuantity(),
            $point->getMesurements()
        ));
        $task->setWeight((int) round($weight * 1000));

        foreach ($point->getPackages() as $package) {
            $this->addPackageToTask($task, $package);
        }

        // The count as the transporter sent it: a type with no package mapping
        // would otherwise only be in the logs.
        if (!empty($point->getPackages())) {
            $task->setMetadata('transporter_packages', array_map(
                fn(TransporterPackage $p) => [
                    'type' => $p->getType()->name,
                    'quantity' => $p->getQuantity(),
                ],
                $point->getPackages()
            ));
        }

        return $task;
    }

    public function buildScontr2PickupTask(
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

    public function buildPickup2DropoffTask(
        Address $address,
        ?EDIFACTMessage $edi = null
    ): Task
    {
        $task = new Task();
        $task->setType(Task::TYPE_DROPOFF);
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

    /**
     * @param array<string,Package> $packageMapping Keyed by
     *   Transporter\Enum\ProductType case name.
     */
    public function setPackageMapping(array $packageMapping): void
    {
        $this->packageMapping = $packageMapping;
    }

    private function addPackageToTask(Task $task, TransporterPackage $package): void
    {
        $productType = $package->getType()->name;

        if (!isset($this->packageMapping[$productType])) {
            $this->transporterLogger->warning(sprintf(
                'No package mapping configured for product type "%s", skipping %d package(s)',
                $productType,
                $package->getQuantity()
            ));
            return;
        }

        $task->addPackageWithQuantity(
            $this->packageMapping[$productType],
            $package->getQuantity()
        );
    }

    /**
     * Reports the address problem found while importing $task, if any.
     * Must be called once $task is persisted.
     *
     * TODO: Close the incident once the task address is fixed. Do it in a
     * handler reacting to a task address change event (if there is one),
     * not in TaskSubscriber::onFlush. For now the dispatcher closes it.
     */
    public function reportAddressIssue(Task $task): void
    {
        if (!isset($this->addressIssues[$task])) {
            return;
        }

        [$description, $metadata] = $this->addressIssues[$task];
        unset($this->addressIssues[$task]);

        $incident = new Incident();
        $incident->setTask($task);
        $incident->setFailureReasonCode('ADDRESS_REVIEW_NEEDED');
        $incident->setPriority(Incident::PRIORITY_HIGH);
        $incident->setTitle($this->translator->trans('transporter.address_review.title', [
            '%address%' => $metadata['transporter_address'],
        ]));
        $incident->setDescription($description);
        $incident->setMetadata([$metadata]);

        ($this->createIncident)($incident, null);
    }

    /**
     * @return array{0:Address,1:array{0:string,1:array<string,string>}|null}
     *   The address, and why it needs a review: a description and metadata
     */
    private function addressFromNAD(
        NameAndAddress $nad
    ): array
    {
        $address = null;
        try {
            $address = $this->geocoder->geocode($nad->getAddress());
        } catch (\Exception $e) {
            $this->transporterLogger->warning(sprintf(
                'Failed to geocode address %s: %s',
                $nad->getAddress(),
                $e->getMessage()
            ));
        }

        $issue = null;
        if (is_null($address)) {
            $this->transporterLogger->warning(sprintf(
                'Geocoding failed for address %s. Fallback to default coordinates',
                $nad->getAddress()
            ));
            $issue = $this->addressIssue('not_found', $nad);
            $address = new Address();
            $address->setGeo($this->defaultCoordinates);
            $address->setStreetAddress('INVALID ADDRESS');
        } elseif (!$this->isInRange($this->defaultCoordinates, $address->getGeo())) {
            // Kept: it may be right, the dispatcher checks it from the incident
            $this->transporterLogger->warning(sprintf(
                'Address %s is not in default range',
                $nad->getAddress()
            ));
            $issue = $this->addressIssue('out_of_range', $nad, $address->getStreetAddress());
        }
        $address->setCompany($nad->getAddressLabel());
        $address->setName($nad->getAddressLabel());
        $address->setContactName($nad->getContactName());
        $address->setTelephone($this->PhoneNumberFromPhone($nad->getCommunicationMeans()));
        return [$address, $issue];
    }

    /**
     * @param string|null $found The geocoded address
     * @return array{0:string,1:array<string,string>}
     */
    private function addressIssue(string $reason, NameAndAddress $nad, ?string $found = null): array
    {
        $metadata = ['transporter_address' => $nad->getAddress()];
        if (!is_null($found)) {
            $metadata['geocoded_address'] = $found;
        }

        return [
            $this->translator->trans(sprintf('transporter.address_review.%s', $reason), [
                '%address%' => $nad->getAddress(),
                '%found%' => $found,
            ]),
            $metadata,
        ];
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
