<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
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
        protected TaskNormalizer $taskNormalizer,
        protected ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory
    )
    {}

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

            // Since API Platform 2.7, IRIs for custom operations have changed
            // It means that when doing PUT /api/orders/{id}/accept, the @id will be /api/orders/{id}/accept, not /api/orders/{id} like before
            // In our JS code, we often override the state with the entire response
            // This custom code makes sure it works like before, by tricking IriConverter
            $context['operation'] = $this->resourceMetadataFactory->create(TaskList::class)->getOperation();

            $data = $this->normalizer->normalize($object, $format, $context);

            if (isset($data['items'])) {
                $data['items'] = $this->flattenItemsUris($data['items']);
            }
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
