<?php

namespace AppBundle\Serializer;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use AppBundle\Service\Tile38Helper;
use AppBundle\Spreadsheet\ParseMetadataTrait;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Doctrine\Persistence\ManagerRegistry;
use Hashids\Hashids;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryNormalizer implements NormalizerInterface, DenormalizerInterface
{
    use ParseMetadataTrait;

    public function __construct(
        private readonly ItemNormalizer $normalizer,
        private readonly Geocoder $geocoder,
        private readonly IriConverterInterface $iriConverter,
        private readonly ManagerRegistry $doctrine,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Hashids $hashids8,
        private readonly Tile38Helper $tile38Helper,
        private readonly TagManager $tagManager
    )
    {
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        $data['trackingUrl'] = $this->urlGenerator->generate('public_delivery', [
            'hashid' => $this->hashids8->encode($object->getId())
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        if (!$object->isCompleted()) {

            $point = $this->tile38Helper->getLastLocationByDelivery($object);

            if (null !== $point) {

                // Warning: format is lng,lat
                [$longitude, $latitude, $timestamp] = $point['coordinates'];

                $data['location'] = [
                    'lat' => $latitude,
                    'lng' => $longitude,
                    'updatedAt' => $timestamp,
                ];
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Delivery;
    }

    private function denormalizeTask($data, Task $task, Delivery $delivery, $format = null)
    {
        if (isset($data['type'])) {
            $task->setType(strtoupper($data['type']));
        }

        // Legacy
        if (isset($data['doneAfter']) && !isset($data['after'])) {
            $data['after'] = $data['doneAfter'];
            unset($data['doneAfter']);
        }
        if (isset($data['doneBefore']) && !isset($data['before'])) {
            $data['before'] = $data['doneBefore'];
            unset($data['doneBefore']);
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

        if (isset($data['address'])) {

            $address = null;
            if (is_string($data['address'])) {
                $addressIRI = $this->iriConverter->getIriFromResource(Address::class);
                if (0 === strpos($data['address'], $addressIRI)) {
                    $address = $this->iriConverter->getResourceFromIri($data['address']);
                } else {
                    $address = $this->geocoder->geocode($data['address']);
                }
            } elseif (is_array($data['address'])) {
                $address = $this->denormalizeAddress($data['address'], $format);
            }

            $task->setAddress($address);
        }

        if (isset($data['comments'])) {
            $task->setComments($data['comments']);
        }

        if (isset($data['tags'])) {
            $task->setTags($data['tags']);
            $this->tagManager->update($task);
        }

        if (isset($data['packages'])) {

            $packageRepository = $this->doctrine->getRepository(Package::class);

            foreach ($data['packages'] as $p) {
                $package = $packageRepository->findOneByNameAndStore($p['type'], $delivery->getStore());
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

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $delivery = $this->normalizer->denormalize($data, $class, $format, $context);

        $inputClass = ($context['input']['class'] ?? null);
        if ($inputClass === DeliveryInput::class) {
            return $delivery;
        }

        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        if (isset($data['tasks']) && is_array($data['tasks'])) {
            if (count($data['tasks']) === 2) {
                $this->denormalizeTask($data['tasks'][0], $pickup, $delivery, $format);
                $this->denormalizeTask($data['tasks'][1], $dropoff, $delivery, $format);
            } else {
                $tasks = array_map(function ($item) use ($delivery, $format) {
                    $task = new Task();
                    $this->denormalizeTask($item, $task, $delivery, $format);
                    return $task;
                }, $data['tasks']);

                $delivery = $delivery->withTasks(...$tasks);
            }
        } else {
            if (isset($data['dropoff'])) {
                $this->denormalizeTask($data['dropoff'], $dropoff, $delivery, $format);
            }

            if (isset($data['pickup'])) {
                $this->denormalizeTask($data['pickup'], $pickup, $delivery, $format);
            }
        }

        if (isset($data['packages'])) {

            $packageRepository = $this->doctrine->getRepository(Package::class);

            foreach ($data['packages'] as $p) {
                $package = $packageRepository->findOneByNameAndStore($p['type'], $delivery->getStore());
                if ($package) {
                    $delivery->addPackageWithQuantity($package, $p['quantity']);
                }
            }
        }

        return $delivery;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Delivery::class;
    }
}
