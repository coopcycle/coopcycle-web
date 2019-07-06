<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $geocoder;
    private $logger;

    public function __construct(
        ItemNormalizer $normalizer,
        Geocoder $geocoder,
        LoggerInterface $logger)
    {
        $this->normalizer = $normalizer;
        $this->geocoder = $geocoder;
        $this->logger = $logger;
    }

    private function normalizeTask(Task $task)
    {
        $address = $this->normalizer->normalize($task->getAddress(), 'jsonld', [
            'resource_class' => Address::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['place']
        ]);

        return [
            'id' => $task->getId(),
            'address' => $address,
            'doneBefore' => $task->getDoneBefore()->format(\DateTime::ATOM),
        ];
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data =  $this->normalizer->normalize($object, $format, $context);

        if (isset($data['items'])) {
            unset($data['items']);
        }

        $data['pickup'] = $this->normalizeTask($object->getPickup());
        $data['dropoff'] = $this->normalizeTask($object->getDropoff());

        $data['color'] = $object->getColor();

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Delivery;
    }

    private function denormalizeTask($data, Task $task)
    {
        if (isset($data['before'])) {
            $task->setDoneBefore(new \DateTime($data['before']));
        } elseif (isset($data['doneBefore'])) {
            $task->setDoneBefore(new \DateTime($data['doneBefore']));
        }

        if (isset($data['address'])) {

            if (is_string($data['address'])) {
                $address = $this->geocoder->geocode($data['address']);
            } elseif (is_array($data['address'])) {
                $address = $this->denormalizeAddress($data['address']);
            }

            $task->setAddress($address);
        }
    }

    private function denormalizeAddress($data)
    {
        $address = new Address();

        if (isset($data['streetAddress'])) {
            $address->setStreetAddress($data['streetAddress']);
        }

        if (isset($data['latLng'])) {
            [ $latitude, $longitude ] = $data['latLng'];
            $address->setGeo(new GeoCoordinates($latitude, $longitude));
        }

        return $address;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $delivery = $this->normalizer->denormalize($data, $class, $format, $context);

        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        if (isset($data['dropoff'])) {
            $this->denormalizeTask($data['dropoff'], $dropoff);
        }

        if (isset($data['pickup'])) {
            $this->denormalizeTask($data['pickup'], $pickup);
        }

        return $delivery;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Delivery::class;
    }
}
