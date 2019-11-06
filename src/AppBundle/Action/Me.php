<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Service\SocketIoManager;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;
use Predis\Client as Redis;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class Me
{
    use TokenStorageTrait;

    protected $doctrine;
    protected $redis;
    protected $socketIoManager;
    protected $logger;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        DoctrineRegistry $doctrine,
        Redis $redis,
        SocketIoManager $socketIoManager,
        LoggerInterface $logger)
    {
        $this->tokenStorage = $tokenStorage;
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->socketIoManager = $socketIoManager;
        $this->logger = $logger;
    }

    /**
     * @Route(
     *   name="my_tasks_list",
     *   path="/me/task_list/{date}",
     *   defaults={
     *     "_api_resource_class"=TaskList::class,
     *     "_api_collection_operation_name"="my_tasks_list"
     *   },
     *   methods={"GET"}
     * )
     */
    public function taskListAction($date)
    {
        $date = new \DateTime($date);

        $taskList = $this->doctrine
            ->getRepository(TaskList::class)
            ->findOneBy([
                'courier' => $this->getUser(),
                'date' => $date
            ]);

        return $taskList;
    }

    /**
     * @Route(
     *   name="my_tasks",
     *   path="/me/tasks/{date}",
     *   defaults={
     *     "_api_resource_class"=Task::class,
     *     "_api_collection_operation_name"="my_tasks"
     *   },
     *   methods={"GET"}
     * )
     */
    public function tasksAction($date)
    {
        $date = new \DateTime($date);

        $taskList = $this->doctrine
            ->getRepository(TaskList::class)
            ->findOneBy([
                'courier' => $this->getUser(),
                'date' => $date
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

    /**
     * @Route(path="/me", name="me",
     *   defaults={
     *     "_api_resource_class"=ApiUser::class,
     *     "_api_collection_operation_name"="me",
     *   },
     *   methods={"GET"}
     * )
     */
    public function meAction()
    {
        return $this->getUser();
    }

    /**
     * @Route(path="/me/location", name="me_location", methods={"POST"})
     */
    public function locationAction(Request $request)
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        $username = $this->getUser()->getUsername();

        if (count($data) === 0) {
            return new JsonResponse([]);
        }

        $data = array_map(function ($location) {
            $location['time'] = ((int) $location['time']) / 1000;
            return $location;
        }, $data);

        usort($data, function ($a, $b) {
            return $a['time'] < $b['time'] ? -1 : 1;
        });

        foreach ($data as $location) {
            $key = sprintf('tracking:%s', $username);
            $this->redis->rpush($key, json_encode([
                'latitude' => (float) $location['latitude'],
                'longitude' => (float) $location['longitude'],
                'timestamp' => (int) $location['time'],
            ]));
        }

        $lastLocation = array_pop($data);

        $datetime = new \DateTime();
        $datetime->setTimestamp($lastLocation['time']);

        $this->logger->info(sprintf('Last position recorded at %s', $datetime->format('Y-m-d H:i:s')));

        $this->socketIoManager->toAdmins('tracking', [
            'user' => $username,
            'coords' => [
                'lat' => (float) $lastLocation['latitude'],
                'lng' => (float) $lastLocation['longitude'],
            ]
        ]);

         $this->socketIoManager->toCourier($this->getUser(), 'tracking', [
            'user' => $username,
            'coords' => [
                'lat' => (float) $lastLocation['latitude'],
                'lng' => (float) $lastLocation['longitude'],
            ]
        ]);

        return new JsonResponse([]);
    }

    /**
     * @Route(path="/me/restaurants", name="me_restaurants",
     *   defaults={
     *     "_api_resource_class"=Restaurant::class,
     *     "_api_collection_operation_name"="me_restaurants",
     *   },
     *   methods={"GET"}
     * )
     */
    public function restaurantsAction(Request $request)
    {
        return $this->getUser()->getRestaurants();
    }

    /**
     * @Route(path="/me/stores", name="me_stores",
     *   defaults={
     *     "_api_resource_class"=Store::class,
     *     "_api_collection_operation_name"="me_stores",
     *   },
     *   methods={"GET"}
     * )
     */
    public function storesAction(Request $request)
    {
        return $this->getUser()->getStores();
    }
}
