<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use AppBundle\Entity\Task;
use AppBundle\Service\TagManager;
use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

final class TasksProvider implements ProviderInterface
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TagManager $tagManager,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        $qb = $this->entityManager->getRepository(Task::class)->createQueryBuilder('o'); // alias 'o' is used by paginator

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection(
                $qb,
                $queryNameGenerator,
                $resourceClass,
                $operation,
                $context
            );

            if (
                $extension instanceof QueryResultCollectionExtensionInterface
                &&
                $extension->supportsResult($resourceClass, $operation, $context)
            ) {
                return $this->postProcessResult(
                    $extension->getResult($qb, $resourceClass, $operation, $context)
                );
            }
        }

        return $this->postProcessResult($qb->getQuery()->getResult());
    }

    private function postProcessResult(iterable $data): iterable
    {
        $tasks = iterator_to_array($data);

        $this->tagManager->warmupCache(...$tasks);

        // Optimization: to avoid extra queries preload one-to-many relations that will be used later
        $this->preloadEntities($tasks);

        return $data;
    }

    private function preloadEntities(array $tasks): void
    {
        $preloader = new EntityPreloader($this->entityManager);

        $preloader->preload($tasks, 'incidents');

        $taskPackage = $preloader->preload($tasks, 'packages');
        $preloader->preload($taskPackage, 'package');

        $delivery = $preloader->preload($tasks, 'delivery');

        // Optimization when calling $delivery->getTasks()
        $taskCollectionItems = $preloader->preload($delivery, 'items');

        $order = $preloader->preload($delivery, 'order');
        $orderItems = $preloader->preload($order, 'items');
        $preloader->preload($orderItems, 'variant');
    }
}
