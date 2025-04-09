<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Task;
use AppBundle\Entity\Package;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use AppBundle\Spreadsheet\ParseMetadataTrait;
use AppBundle\Utils\Barcode\BarcodeUtils;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TaskNormalizer implements NormalizerInterface, DenormalizerInterface
{
    use ParseMetadataTrait;

    public function __construct(
        private readonly ItemNormalizer $normalizer,
        private readonly IriConverterInterface $iriConverter,
        private readonly TagManager $tagManager,
        private readonly UserManagerInterface $userManager,
        private readonly Geocoder $geocoder,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $country,
        private readonly LoggerInterface $logger
    )
    {}

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data)) {

            return $data;
        }

        // Legacy props
        if (isset($data['after'])) {
            $data['doneAfter'] = $data['after'];
        }
        if (isset($data['before'])) {
            $data['doneBefore'] = $data['before'];
        }

        // Make sure "comments" is a string
        if (array_key_exists('comments', $data) && null === $data['comments']) {
            $data['comments'] = '';
        }

        if (isset($data['tags']) && is_array($data['tags']) && count($data['tags']) > 0) {
            $data['tags'] = $this->tagManager->expand($data['tags']);
        }

        // FIXME Avoid coupling normalizer with groups
        // https://medium.com/@rebolon/the-symfony-serializer-a-great-but-complex-component-fbc09baa65a0
        if (in_array('task', $context['groups'])) {

            $data['assignedTo'] = null;
            if ($object->isAssigned()) {
                $data['assignedTo'] = $object->getAssignedCourier()->getUsername();
            }

            $data['previous'] = null;
            if ($object->hasPrevious()) {
                $data['previous'] = $this->iriConverter->getIriFromItem($object->getPrevious());
            }

            $data['next'] = null;
            if ($object->hasNext()) {
                $data['next'] = $this->iriConverter->getIriFromItem($object->getNext());
            }
        }

        $barcode = BarcodeUtils::getRawBarcodeFromTask($object);
        $barcode_token = BarcodeUtils::getToken($barcode);
        $data['barcode'] = [
            'barcode' => $barcode,
            'label' => [
                'token' => $barcode_token,
                'url' => $this->urlGenerator->generate(
                    'task_label_pdf',
                    ['code' => $barcode, 'token' => $barcode_token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            ]
        ];

        $data['packages'] = [];

        if (!is_null($object->getPrefetchedPackagesAndWeight())) {
            $data['packages'] = !is_null($object->getPrefetchedPackagesAndWeight()['packages']) ? $object->getPrefetchedPackagesAndWeight()['packages'] : [];
            $data['weight'] = $object->getPrefetchedPackagesAndWeight()['weight'];
        } elseif ($object->isPickup()) {
            // for a pickup in a delivery, the serialized weight is the sum of the dropoff weight and the packages are the "sum" of the dropoffs packages
            $delivery = $object->getDelivery();

            if (null !== $delivery) {
                $deliveryId = $delivery->getId();

                $qb =  $this->entityManager
                    ->getRepository(Task::class)
                    ->createQueryBuilder('t');

                $query = $qb
                    ->select('p.id', 'MAX(tp.id) as task_package_id', 'p.name AS name', 'p.name AS type', 'sum(tp.quantity) AS quantity', 'p.averageVolumeUnits AS volume_per_package', 'p.shortCode AS short_code')
                    ->join('t.packages', 'tp', 'WITH', 'tp.task = t.id')
                    ->join('tp.package', 'p', 'WITH', 'tp.package = p.id')
                    ->join('t.delivery', 'd', 'WITH', 'd.id = :deliveryId')
                    ->groupBy('p.id', 'p.name', 'p.averageVolumeUnits', 'p.shortCode')
                    ->setParameter('deliveryId', $deliveryId)
                    ->getQuery();

                $data['packages'] = $query->getResult();

                $qbWeight =  $this->entityManager
                    ->getRepository(Task::class)
                    ->createQueryBuilder('t');

                $data['weight'] = $qbWeight
                    ->select('sum(t.weight)')
                    ->join('t.delivery', 'd', 'WITH', 'd.id = :deliveryId')
                    ->setParameter('deliveryId', $deliveryId)
                    ->groupBy('d.id')
                    ->getQuery()
                    ->getResult()[0]["1"];
            }
        } else {

            $qb =  $this->entityManager
                ->getRepository(Task::class)
                ->createQueryBuilder('t');

            $data['packages'] = $qb
                ->select('p.id', 'tp.id as task_package_id', 'p.name AS name', 'p.name AS type', 'tp.quantity AS quantity', 'p.averageVolumeUnits AS volume_per_package', 'p.shortCode AS short_code')
                ->join('t.packages', 'tp', 'WITH', 'tp.task = t.id')
                ->join('tp.package', 'p', 'WITH', 'tp.package = p.id')
                ->andWhere('t.id = :taskId')
                ->setParameter('taskId', $object->getId())
                ->getQuery()
                ->getResult();
        }

        // Add labels
        foreach ($data['packages'] as $i => $p) {

            $data['packages'][$i]['labels'] = [];

            $barcodes = BarcodeUtils::getBarcodesFromTaskAndPackageIds($object->getId(), $p['task_package_id'], $p['quantity']);
            foreach ($barcodes as $barcode) {
                $labelUrl = $this->urlGenerator->generate(
                    'task_label_pdf',
                    ['code' => $barcode, 'token' => BarcodeUtils::getToken($barcode)],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                $data['packages'][$i]['labels'][] = $labelUrl;
            }

            unset($data['packages'][$i]['id']);
            unset($data['packages'][$i]['task_package_id']);
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata']['zero_waste'] = $object->isZeroWaste();
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Task;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        // Legacy props
        if (isset($data['doneAfter']) && !isset($data['after'])) {
            $data['after'] = $data['doneAfter'];
            unset($data['doneAfter']);
        }
        if (isset($data['doneBefore']) && !isset($data['before'])) {
            $data['before'] = $data['doneBefore'];
            unset($data['doneBefore']);
        }

        $address = null;
        if (isset($data['address'])) {

            $address = null;
            if (is_string($data['address'])) {
                $addressIRI = $this->iriConverter->getIriFromResourceClass(Address::class);
                if (0 === strpos($data['address'], $addressIRI)) {
                    $address = $this->iriConverter->getItemFromIri($data['address']);
                } else {
                    $address = $this->geocoder->geocode($data['address']);
                    unset($data['address']);
                }
            } elseif (is_array($data['address'])) {
                $address = $this->denormalizeAddress($data['address'], $format);
            }
        }

        if (isset($data['type'])) {
            $data['type'] = strtoupper($data['type']);
            // Ignore weight & packages for pickup tasks
            // @see https://github.com/coopcycle/coopcycle-web/issues/3461
            if ($data['type'] === 'PICKUP') {
                unset($data['weight']);
                unset($data['packages']);
            }
        }

        /**
         * @var Task $task
         */
        $task = $this->normalizer->denormalize($data, $class, $format, $context);

        if (null === $task->getId() && null !== $task->getAddress()) {
            $addr = $task->getAddress();
            if (!empty($addr->getStreetAddress()) && null === $addr->getGeo()) {
                $geoAddr = $this->geocoder->geocode($addr->getStreetAddress());
                $addr->setGeo($geoAddr->getGeo());
            }
        }

        if ($address && null === $task->getAddress()) {
            $task->setAddress($address);
        }

        if (isset($data['assignedTo'])) {
            $user = $this->userManager->findUserByUsername($data['assignedTo']);
            if ($user && $user->hasRole('ROLE_COURIER')) {
                $task->assignTo($user);
            }
        }

        /**
         * @var TimeSlot $timeSlot
         */
        $timeSlot = null;

        if (isset($data['timeSlotUrl'])) {
            try {
                $timeSlot = $this->iriConverter->getItemFromIri($data['timeSlotUrl']);
            } catch (InvalidArgumentException $e) {
                $this->logger->warning('Invalid time slot URL: ' . $data['timeSlotUrl']);
                throw new InvalidArgumentException('task.timeSlotUrl.invalid');
            }

            $task->setTimeSlot($timeSlot);
        }

        if (isset($data['timeSlot'])) {

            /**
             * @var TsRange $range
             */
            $range = null;

            //example: 2024-01-01 14:30-18:45
            if (1 === preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}) ([0-9:]+-[0-9:]+)$/', $data['timeSlot'], $matches)) {

                $date = $matches[1];
                $timeRange = $matches[2];

                [ $start, $end ] = explode('-', $timeRange);

                [ $startHour, $startMinute ] = explode(':', $start);
                [ $endHour, $endMinute ] = explode(':', $end);

                $after = new \DateTime($date);
                $after->setTime($startHour, $startMinute);

                $before = new \DateTime($date);
                $before->setTime($endHour, $endMinute);

                $range = TsRange::create($after, $before);
            } else {

                //example: 2022-08-12T10:00:00Z/2022-08-12T12:00:00Z

                $tz = date_default_timezone_get();

                // FIXME Catch Exception
                $period = CarbonPeriod::createFromIso($data['timeSlot']);

                $after = $period->getStartDate()->tz($tz)->toDateTime();
                $before = $period->getEndDate()->tz($tz)->toDateTime();

                $range = TsRange::create($after, $before);
            }

            // Validate that the input time slot was selected from the given list of time slot choices (timeSlotUrl)
            if (null !== $timeSlot) {
                $choiceLoader = new TimeSlotChoiceLoader($timeSlot, $this->country);
                $choiceList = $choiceLoader->loadChoiceList();

                $choices = array_filter(
                    $choiceList->getChoices(),
                    function ($choice) use ($range) {
                        return $choice->contains($range);
                    }
                );

                if (0 === count($choices)) {
                    $this->logger->warning('Invalid time slot range: ' . $data['timeSlot']);
                    throw new InvalidArgumentException('task.timeSlot.invalid');
                }
            }

            $task->setAfter($range->getLower());
            $task->setBefore($range->getUpper());
        } elseif (isset($data['before']) || isset($data['after'])) {

            $tz = date_default_timezone_get();

            if (isset($data['after'])) {
                $task->setAfter(
                    Carbon::parse($data['after'])->tz($tz)->toDateTime()
                );
            }
            if (isset($data['before'])) {
                $task->setBefore(
                    Carbon::parse($data['before'])->tz($tz)->toDateTime()
                );
            }
        }

        if (isset($data['comments'])) {
            $task->setComments($data['comments']);
        }

        if (isset($data['tags'])) {
            $task->setTags($data['tags']);
            $this->tagManager->update($task);
        }

        if (isset($data['packages'])) {

            $packageRepository = $this->entityManager->getRepository(Package::class);

            foreach ($data['packages'] as $p) {
                //FIXME: does this actually work? $task->getStore() is probably null at this point
                $package = $packageRepository->findOneByNameAndStore($p['type'], $task->getStore());
                if ($package) {
                    $task->setQuantityForPackage($package, $p['quantity']);
                }
            }
        }

        if (isset($data['weight'])) {
            $task->setWeight($data['weight']);
        }

        if (isset($data['metadata']) && is_string($data['metadata'])) { // we support here metadata send as a string from a CSV file
            $this->parseAndApplyMetadata($task, $data['metadata']);
        }

        return $task;
    }

    private function denormalizeAddress($data, $format = null)
    {
        $address = $this->normalizer->denormalize($data, Address::class, $format);

        if (null === $address->getGeo()) {
            if (isset($data['latLng'])) {
                [ $latitude, $longitude ] = $data['latLng'];
                $address->setGeo(new GeoCoordinates($latitude, $longitude));
            } else {
                $geocoded = $this->geocoder->geocode($address->getStreetAddress());
                $address->setGeo($geocoded->getGeo());
            }
        }

        return $address;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Task::class;
    }
}
