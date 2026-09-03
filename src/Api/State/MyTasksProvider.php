<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\MyTaskListDto;
use AppBundle\Entity\TaskListRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class MyTasksProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly TaskListRepository $taskListRepository,
        private readonly RequestStack $requestStack)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        $date = $uriVariables['date'];

        // Opt-in: without "?tours=1" the tours are flattened into a list of tasks,
        // which is what every app version released before tours support expects.
        // Do NOT make this the default, it would break already installed apps.
        $request = $this->requestStack->getCurrentRequest();
        $withTours = $request?->query->getBoolean('tours') ?? false;

        $taskListDto = $this->taskListRepository->findMyTaskListAsDto($user, $date, $withTours);

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
