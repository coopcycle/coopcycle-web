<?php

namespace AppBundle\Api\DataProvider;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Excetion\ResourceClassNotSupportedException;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class TaskCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    use TokenStorageTrait;

    public function __construct(
        RequestStack $requestStack,
        ManagerRegistry $doctrine,
        TokenStorageInterface $tokenStorage)
    {
        $this->requestStack = $requestStack;
        $this->taskListRepository = $doctrine->getRepository(TaskList::class);
        $this->tokenStorage = $tokenStorage;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Task::class === $resourceClass && 'my_tasks' === $operationName;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $request = $this->requestStack->getCurrentRequest();

        $taskList = $this->taskListRepository->findOneBy([
            'courier' => $this->getUser(),
            'date' => new \DateTime($request->attributes->get('date'))
        ]);

        $tasks = [];
        if ($taskList) {
            $tasks = $taskList->getTasks();
            $tasks = array_filter($tasks, function (Task $task) {
                return !$task->isCancelled();
            });
        }

        return $tasks;
    }
}
