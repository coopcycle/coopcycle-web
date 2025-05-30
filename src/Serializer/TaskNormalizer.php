<?php

namespace AppBundle\Serializer;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\Package;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use AppBundle\Utils\Barcode\BarcodeUtils;
use Carbon\CarbonPeriod;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TaskNormalizer implements NormalizerInterface, ContextAwareDenormalizerInterface
{
    public function __construct(
        private readonly ItemNormalizer $normalizer,
        private readonly IriConverterInterface $iriConverter,
        private readonly TagManager $tagManager,
        private readonly UserManagerInterface $userManager,
        private readonly Geocoder $geocoder,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly LoggerInterface $logger
    )
    {}

    public function normalize($object, $format = null, array $context = array())
    {
        // Since API Platform 2.7, IRIs for custom operations have changed
        // It means that when doing PUT /api/tasks/{id}/assign, the @id will be /api/tasks/{id}/assign, not /api/tasks/{id} like before
        // In our JS code, we often override the state with the entire response
        // This custom code makes sure it works like before, by tricking IriConverter
        $context['operation'] = $this->resourceMetadataFactory->create(Task::class)->getOperation();

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
                $data['previous'] = $this->iriConverter->getIriFromResource($object->getPrevious());
            }

            $data['next'] = null;
            if ($object->hasNext()) {
                $data['next'] = $this->iriConverter->getIriFromResource($object->getNext());
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
        /**
         * FIXME: Avoid using this method in the new code
         * It exists only to support legacy use cases
         * Prefer using the DeliveryInput/DeliveryInputDataTransformer instead
         */

        $this->logger->info('Deprecated: TaskNormalizer::denormalize', [
            'class' => $class,
            'data' => $data,
            'context' => $context,
        ]);

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
                $this->iriConverter->getResourceFromIri($data['address']);
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
                $package = $packageRepository->findOneByNameAndStore($p['type'], $task->getStore());
                if ($package) {
                    $task->setQuantityForPackage($package, $p['quantity']);
                }
            }
        }

        return $task;
    }

    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        if (isset($context['input'])) {
            return false;
        }

        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Task::class;
    }
}
