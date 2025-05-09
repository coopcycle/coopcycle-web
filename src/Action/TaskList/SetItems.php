<?php

namespace AppBundle\Action\TaskList;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use AppBundle\Entity\User;
use AppBundle\Service\TaskListManager;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use Symfony\Component\HttpFoundation\Request;

final class SetItems
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserManager $userManager,
        private TaskListManager $taskListManager,
        private TaskListProvider $taskListProvider
    )
    {}

    public function __invoke(Request $request)
    {
        $date = new \DateTime($request->get('date'));
        $user = $this->userManager->findUserByUsername($request->get('username'));

        $taskList = $this->taskListProvider->getTaskListForUserAndDate($date, $user);

        $preloader = new EntityPreloader($this->entityManager);
        $preloader->preload($taskList->getTasks(), 'assignedTo');

        // Tasks are sent as JSON payload
        $data = $request->toArray();

        $this->taskListManager->assign($taskList, $data['items']);

        return $taskList;
    }
}
