<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ObjectNormalizer;
use AppBundle\Api\Dto\MyTaskList;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Tour;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MyTaskListNormalizer implements NormalizerInterface
{

    public function __construct(
        protected ObjectNormalizer $normalizer,
        protected IriConverterInterface $iriConverterInterface
    )
    {
        $this->normalizer = $normalizer;
    }

//    private function flattenItemsUris(array $items)
//    {
//        $itemsUris = [];
//        foreach($items as $item) {
//            if (isset($item['task'])) {
//                array_push(
//                    $itemsUris,
//                    $item['task']
//                );
//            } else {
//                array_push(
//                    $itemsUris,
//                    $item['tour']['@id'], // to the best of my knowledge, tour is eagerly fetch because we use the table inheritance for the tour table
//                );
//            }
//        }
//
//        return $itemsUris;
//    }

    public function normalize($object, $format = null, array $context = array())
    {
        // legacy serialization for API endpoints that output TaskList.items as a list of tasks
        // look for "setTempLegacyTaskStorage" usage in the code.
        // known usage at the time of the writing :
        //  - used for the rider/dispatcher smartphone app (does not display or handle tours)
        //  - used for stores to access /api/task_lists/ and display only tasks linked to the org (it is only used by Tricargo coop and should be considered legacy)
        if ($object->isTempLegacyTaskStorage) {
            $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = ['items'];
            $data = $this->normalizer->normalize($object, $format, $context);

            // override json-ld to match the existing API
            $data['@context'] = '/api/contexts/TaskList';
            $data['@type'] = 'TaskList';
            $data['@id'] = "/api/task_lists/" . $object->id;

            $data['items'] = array_map(function($task) {
                $taskData = $this->normalizer->normalize(
                    $task,
                    'jsonld',
                    ['groups' => ["task_list", "task_collection", "task", "delivery", "address"]]
                );

                // override json-ld to match the existing API
                $taskData['@context'] = '/api/contexts/Task';
                $taskData['@type'] = 'Task';
                $taskData['@id'] = "/api/tasks/" . $task->id;

                return $taskData;
                }, $object->items
            );
        }
        // legacy serialization for app and events
        // see https://github.com/coopcycle/coopcycle-app/issues/1803
        else if (in_array('task', $context['groups'])) {
            $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = ['items'];
            $data = $this->normalizer->normalize($object, $format, $context);
            $data['items'] = array_map(function($task) {
                return $this->normalizer->normalize(
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
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof MyTaskList;
    }
}
