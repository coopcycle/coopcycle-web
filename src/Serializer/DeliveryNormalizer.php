<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Api\Dto\DeliveryInput;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use AppBundle\Service\Tile38Helper;
use AppBundle\Spreadsheet\ParseMetadataTrait;
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
        private readonly TagManager $tagManager,
        private readonly TaskNormalizer $taskNormalizer,
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

    private function denormalizeTask($data, Delivery $delivery, $format = null, array $context = array())
    {
        $task = $this->taskNormalizer->denormalize($data, Task::class, $format, $context);

        if (isset($data['packages'])) {

            $packageRepository = $this->doctrine->getRepository(Package::class);

            foreach ($data['packages'] as $p) {
                $package = $packageRepository->findOneByNameAndStore($p['type'], $delivery->getStore());
                if ($package) {
                    $task->setQuantityForPackage($package, $p['quantity']);
                }
            }
        }

        return $task;
    }

        public function denormalize($data, $class, $format = null, array $context = array())
    {
        /**
         * @var Delivery $delivery
         */
        $delivery = $this->normalizer->denormalize($data, $class, $format, $context);

        $inputClass = ($context['input']['class'] ?? null);
        if ($inputClass === DeliveryInput::class) {
            return $delivery;
        }


        if (isset($data['tasks']) && is_array($data['tasks'])) {
            $tasks = array_map(function ($item) use ($delivery, $format, $context) {
                $task = $this->denormalizeTask($item, $delivery, $format, $context);
                return $task;
            }, $data['tasks']);

            $delivery = $delivery->withTasks(...$tasks);
        } else {
            $tasks = [];

            if (isset($data['pickup'])) {
                $tasks[] = $this->denormalizeTask($data['pickup'], $delivery, $format, $context);
            }

            if (isset($data['dropoff'])) {
                $tasks[] = $this->denormalizeTask($data['dropoff'], $delivery, $format, $context);
            }

            $delivery = $delivery->withTasks(...$tasks);
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
