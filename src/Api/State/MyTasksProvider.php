<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\MyTaskListDto;
use AppBundle\Entity\TaskListRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class MyTasksProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly TaskListRepository $taskListRepository)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        $date = $uriVariables['date'];

        $taskListDto = $this->taskListRepository->findMyTaskListAsDto($user, $date);

        if (null === $taskListDto) {
            // Do NOT create an empty TaskList in the database,
            // it would add the courier to the dispatch for that day
            $now = new \DateTime();

            return new MyTaskListDto(
                0,
                $now,
                $now,
                $date,
                $user->getUsername(),
                [],
                0,
                0,
                ''
            );
        }

        return $taskListDto;
    }
}
