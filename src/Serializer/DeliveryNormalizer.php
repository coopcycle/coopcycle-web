<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use Carbon\CarbonPeriod;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $geocoder;
    private $doctrine;

    public function __construct(
        ItemNormalizer $normalizer,
        Geocoder $geocoder,
        IriConverterInterface $iriConverter,
        ManagerRegistry $doctrine)
    {
        $this->normalizer = $normalizer;
        $this->geocoder = $geocoder;
        $this->iriConverter = $iriConverter;
        $this->doctrine = $doctrine;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Delivery;
    }

    private function denormalizeTask($data, Task $task, $format = null)
    {
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

        } elseif (isset($data['before'])) {

            $task->setDoneBefore(new \DateTime($data['before']));
        } elseif (isset($data['doneBefore'])) {

            $task->setDoneBefore(new \DateTime($data['doneBefore']));
        }

        if (isset($data['address'])) {

            $address = null;
            if (is_string($data['address'])) {
                $addressIRI = $this->iriConverter->getIriFromResourceClass(Address::class);
                if (0 === strpos($data['address'], $addressIRI)) {
                    $address = $this->iriConverter->getItemFromIri($data['address']);
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

        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        if (isset($data['dropoff'])) {
            $this->denormalizeTask($data['dropoff'], $dropoff, $format);
        }

        if (isset($data['pickup'])) {
            $this->denormalizeTask($data['pickup'], $pickup, $format);
        }

        if (isset($data['packages'])) {

            $packageRepository = $this->doctrine->getRepository(Package::class);

            foreach ($data['packages'] as $p) {
                $package = $packageRepository->findOneByName($p['type']);
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
