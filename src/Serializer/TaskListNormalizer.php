<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Tour;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TaskListNormalizer implements NormalizerInterface, DenormalizerInterface
{

    public function __construct(
        protected ItemNormalizer $normalizer,
        protected IriConverterInterface $iriConverterInterface,
        protected TaskNormalizer $taskNormalizer
    )
    {
        $this->normalizer = $normalizer;
    }

    private function flattenItemsUris(array $items)
    {
        $itemsUris = [];
        foreach($items as $item) {
            if (isset($item['task'])) {
                array_push(
                    $itemsUris,
                    $item['task']
                );
            } else {
                array_push(
                    $itemsUris,
                    $item['tour']['@id'], // to the best of my knowledge, tour is eagerly fetch because we use the table inheritance for the tour table
                );
            }
        }

        return $itemsUris;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        // legacy serialization for API endpoints that output TaskList.items as a list of tasks
        // look for "setTempLegacyTaskStorage" usage in the code.
        // known usage at the time of the writing :
        //  - used for the rider/dispatcher smartphone app (does not display or handle tours)
        //  - used for stores to access /api/task_lists/ and display only tasks linked to the org (it is only used by Tricargo coop and should be considered legacy)
        if (!is_null($object->getTempLegacyTaskStorage())) {
            $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = ['items'];
            $data = $this->normalizer->normalize($object, $format, $context);
            $data['items'] = array_map(function($task) {
                return $this->taskNormalizer->normalize(
                    $task,
                    'jsonld',
                    ['groups' => ["task_list", "task_collection", "task", "delivery", "address"]]
                );
                }, $object->getTempLegacyTaskStorage()
            );
        }
        // legacy serialization for app and events
        // see https://github.com/coopcycle/coopcycle-app/issues/1803
        else if (in_array('task', $context['groups'])) {
            $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = ['items'];
            $data = $this->normalizer->normalize($object, $format, $context);
            $data['items'] = array_map(function($task) {
                return $this->taskNormalizer->normalize(
                    $task,
                    'jsonld',
                    ['groups' => ["task_list", "task_collection", "task", "delivery", "address"]]
                );
                }, $object->getTasks()
            );
        } else  {
            $data = $this->normalizer->normalize($object, $format, $context);

            if (isset($data['items'])) {
                $data['items'] = $this->flattenItemsUris($data['items']);
            }
        }

        // Legacy
        if (isset($context['item_operation_name']) && $context['item_operation_name'] === 'my_tasks') {
            $data['hydra:member'] = $data['items'];
            $data['hydra:totalItems'] = count($data['items']);
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof TaskList;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return $this->normalizer->denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === TaskList::class;
    }
}
