<?php

namespace AppBundle\Serializer\Csv;

use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Serializer\TaskNormalizer as BaseTaskNormalizer;
use AppBundle\Sylius\Order\AdjustmentInterface;
use ApiPlatform\Core\Serializer\ItemNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class TaskNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(BaseTaskNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        return null;
    }

    public function supportsNormalization($data, $format = null)
    {
        return false;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $isObject = array_keys($data) !== range(0, count($data) - 1);

        if ($isObject) {
            $data = [ $data ];
        }

        return array_map(function ($item) use ($context) {

            // This is needed, because CsvDecoder will transform empty rows to empty strings,
            // causing the error "The string supplied did not seem to be a phone number."
            if (isset($item['address'], $item['address']['telephone']) && empty($item['address']['telephone'])) {
                unset($item['address']['telephone']);
            }

            return $this->normalizer->denormalize($item, Task::class, 'jsonld', $context);
        }, $data);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return TaskGroup::class === $type && 'csv' === $format;
    }
}
