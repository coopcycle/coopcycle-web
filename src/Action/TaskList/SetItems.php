<?php

namespace AppBundle\Action\TaskList;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use AppBundle\Entity\User;
use AppBundle\Serializer\TaskListNormalizer;
use AppBundle\Service\TaskListManager;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class SetItems
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserManager $userManager,
        private TaskListManager $taskListManager,
        private TaskListNormalizer $taskListNormalizer,
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
        $data = json_decode($request->getContent(), true);

        $this->taskListManager->assign($taskList, $data['items']);

        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        return new JsonResponse($this->taskListNormalizer->normalize($taskList, 'jsonld', [
            'resource_class' => TaskList::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task_list']
        ]));
    }
}
