<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Task;
use AppBundle\Entity\Package;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use Carbon\CarbonPeriod;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TaskNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private ItemNormalizer $normalizer,
        private IriConverterInterface $iriConverter,
        private TagManager $tagManager,
        private UserManagerInterface $userManager,
        private Geocoder $geocoder,
        private EntityManagerInterface $entityManager)
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

        if (!is_null($object->getPrefetchedPackagesAndWeight())) {
            $data['packages'] = !is_null($object->getPrefetchedPackagesAndWeight()['packages']) ? $object->getPrefetchedPackagesAndWeight()['packages'] : [];
            $data['weight'] = $object->getPrefetchedPackagesAndWeight()['weight'];
        } else if ($object->isPickup()) {
            // for a pickup in a delivery, the serialized weight is the sum of the dropoff weight and the packages are the "sum" of the dropoffs packages
            $delivery = $object->getDelivery();

            if (null !== $delivery) {
                $deliveryId = $delivery->getId();

                $qb =  $this->entityManager
                    ->getRepository(Task::class)
                    ->createQueryBuilder('t');

                $query = $qb
                    ->select('p.name AS name', 'p.name AS type', 'sum(tp.quantity) AS quantity', 'p.volumeUnits AS volume_per_package')
                    ->join('t.packages', 'tp', 'WITH', 'tp.task = t.id')
                    ->join('tp.package', 'p', 'WITH', 'tp.package = p.id')
                    ->join('t.delivery', 'd', 'WITH', 'd.id = :deliveryId')
                    ->groupBy('p.id', 'p.name', 'p.volumeUnits')
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
                ->select('p.name AS name', 'p.name AS type', 'tp.quantity AS quantity', 'p.volumeUnits AS volume_per_package')
                ->join('t.packages', 'tp', 'WITH', 'tp.task = t.id')
                ->join('tp.package', 'p', 'WITH', 'tp.package = p.id')
                ->andWhere('t.id = :taskId')
                ->setParameter('taskId', $object->getId())
                ->getQuery()
                ->getResult();
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
        if (isset($data['address']) && is_string($data['address'])) {
            try {
                $this->iriConverter->getItemFromIri($data['address']);
            } catch (InvalidArgumentException $e) {
                $addressAsString = $data['address'];
                unset($data['address']);
                $address = $this->geocoder->geocode($addressAsString);
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

        if (isset($data['timeSlot'])) {

            // TODO Validate time slot

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

                $task->setAfter($after);
                $task->setBefore($before);

            } else {

                $tz = date_default_timezone_get();

                // FIXME Catch Exception
                $period = CarbonPeriod::createFromIso($data['timeSlot']);

                $task->setAfter($period->getStartDate()->tz($tz)->toDateTime());
                $task->setBefore($period->getEndDate()->tz($tz)->toDateTime());

            }
        }

        if (isset($data['packages'])) {

            $packageRepository = $this->entityManager->getRepository(Package::class);

            foreach ($data['packages'] as $p) {
                $package = $packageRepository->findOneByName($p['type']);
                if ($package) {
                    $task->setQuantityForPackage($package, $p['quantity']);
                }
            }
        }

        return $task;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Task::class;
    }
}
