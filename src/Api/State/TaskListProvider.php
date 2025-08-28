<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\TaskList;
use AppBundle\Security\TokenStoreExtractor;

/**
 * Custom list collection data provider that is able to retrieve task lists
 * with only tasks belonging to a store.
 */
final class TaskListProvider implements ProviderInterface
{
    public function __construct(
        private CollectionProvider $provider,
        private TokenStoreExtractor $extractor
    )
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $collection = $this->provider->provide($operation, $uriVariables, $context);

        $store = $this->extractor->extractStore();

        if (null === $store) {

            return $collection;
        }

        foreach ($collection as $taskList) {

            $tasks = $taskList->getTasks();

            // reset array index to 0 with array_values, otherwise you might get weird stuff in the serializer
            $storeTasks = array_values(array_filter($tasks, function ($task) use ($store) {
                return $store->getOrganization() === $task->getOrganization();
            }));

            $taskList->setTempLegacyTaskStorage($storeTasks);
        }

        return $collection;
    }
}
