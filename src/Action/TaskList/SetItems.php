<?php

namespace AppBundle\Action\TaskList;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use AppBundle\Entity\User;
use AppBundle\Serializer\TaskListNormalizer;
use AppBundle\Service\TaskListManager;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class SetItems
{
    public function __construct(
        protected EntityManagerInterface $objectManager,
        protected UserManager $userManager,
        protected IriConverterInterface $iriConverter,
        protected TaskListManager $taskListManager,
        protected TaskListNormalizer $taskListNormalizer
    )
    {
    }

    protected function getTaskList(\DateTime $date, User $user)
    {
        $taskList = $this->objectManager
            ->getRepository(TaskList::class)
            ->findOneBy(['date' => $date, 'courier' => $user]);

        if (null === $taskList) {
            $taskList = new TaskList();
            $taskList->setDate($date);
            $taskList->setCourier($user);
        }

        return $taskList;
    }

    public function __invoke(Request $request)
    {
        $date = new \DateTime($request->get('date'));
        $user = $this->userManager->findUserByUsername($request->get('username'));

        $taskList = $this->getTaskList($date, $user);

        if (null === $taskList->getId()) {
            $this->objectManager->persist($taskList);
        }

        // Tasks are sent as JSON payload
        $data = json_decode($request->getContent(), true);
        foreach ($data['items'] as $key => $taskOrTour) {
            $taskOrTour = $this->iriConverter->getItemFromIri($taskOrTour);
            $item = new Item();
            $item->setPosition($key);
            if ($taskOrTour instanceof Tour) {
                $item->setTour($taskOrTour);
            } else {
                $item->setTask($taskOrTour);
            }
            $items[] = $item;
        }

        $this->taskListManager->assign($taskList, $items);

        $this->objectManager->flush();

        return new JsonResponse($this->taskListNormalizer->normalize($taskList, 'jsonld', [
            'resource_class' => TaskList::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task_list']
        ]));
    }
}