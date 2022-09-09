<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\CollectionDataProvider;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use AppBundle\Entity\TaskList;
use AppBundle\Security\TokenStoreExtractor;

/**
 * Custom list collection data provider that is able to retrieve task lists
 * with only tasks belonging to a store.
 */
final class TaskListCollectionDataProvider extends CollectionDataProvider
{
    private $extractor;

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return TaskList::class === $resourceClass && $operationName === 'get';
    }

    public function setTokenExtractor(TokenStoreExtractor $extractor)
    {
        $this->extractor = $extractor;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $collection = parent::getCollection($resourceClass, $operationName, $context);

        $store = $this->extractor->extractStore();

        if (null === $store) {

            return $collection;
        }

        foreach ($collection as $taskList) {

            $tasks = $taskList->getTasks();

            $storeTasks = array_filter($tasks, function ($task) use ($store) {

                return $store->getOrganization() === $task->getOrganization();
            });

            $taskList->setTasks($storeTasks);
        }

        return $collection;
    }
}
