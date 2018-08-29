<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use AppBundle\Service\RoutingInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $geocoder;
    private $tokenStorage;
    private $logger;

    public function __construct(
        ItemNormalizer $normalizer,
        Geocoder $geocoder,
        TokenStorageInterface $tokenStorage,
        RoutingInterface $routing,
        LoggerInterface $logger)
    {
        $this->normalizer = $normalizer;
        $this->geocoder = $geocoder;
        $this->tokenStorage = $tokenStorage;
        $this->routing = $routing;
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
        if (isset($data['doneBefore'])) {
            $task->setDoneBefore(new \DateTime($data['doneBefore']));
        }

        if (isset($data['address'])) {
            $address = $this->geocoder->geocode($data['address']);
            $task->setAddress($address);
        }
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

        // If no pickup address is specified, use the store address
        if (null === $pickup->getAddress()) {
            if (null === $token = $this->tokenStorage->getToken()) {
                // TODO Throw Exception
            }
            if (null === $store = $token->getAttribute('store')) {
                // TODO Throw Exception
            }
            $pickup->setAddress($store->getAddress());
        }

        // If no pickup time is specified, calculate it
        if (null === $pickup->getDoneBefore()) {
            if (null !== $dropoff->getAddress() && null !== $pickup->getAddress()) {

                $duration = $this->routing->getDuration(
                    $pickup->getAddress()->getGeo(),
                    $dropoff->getAddress()->getGeo()
                );

                $pickupDoneBefore = clone $dropoff->getDoneBefore();
                $pickupDoneBefore->modify(sprintf('-%d seconds', $duration));

                $pickup->setDoneBefore($pickupDoneBefore);
            }
        }

        return $delivery;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Delivery::class;
    }
}
