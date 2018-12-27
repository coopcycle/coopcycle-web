<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\RemotePushToken;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Form\TaskExportType;
use AppBundle\Form\TaskGroupType;
use AppBundle\Form\TaskType;
use AppBundle\Form\TaskUploadType;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\SocketIoManager;
use AppBundle\Service\TaskManager;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\RequestContext;

trait AdminDashboardTrait
{
    protected function redirectToDashboard(Request $request)
    {
        $nav = $request->query->getBoolean('nav', true);

        $params = [
            'date' => $request->get('date'),
        ];

        if (!$nav) {
            $params['nav'] = 'off';
        }

        return $this->redirectToRoute('admin_dashboard_fullscreen', $params);
    }

    protected function notifyTasksChanged(
        UserInterface $user,
        \DateTime $date,
        array $normalizedTasks,
        RemotePushNotificationManager $remotePushNotificationManager,
        SocketIoManager $socketIoManager)
    {
        $remotePushTokenRepository = $this->getDoctrine()->getRepository(RemotePushToken::class);

        $tokens = $remotePushTokenRepository->findByUser($user);

        // We can't send the whole serialized tasks,
        // because the JSON payload is limited in size
        $data = [
            'event' => [
                'name' => 'tasks:changed',
                'data' => [
                    'date' => $date->format('Y-m-d')
                ]
            ]
        ];

        foreach ($tokens as $token) {
            $remotePushNotificationManager
                ->send(sprintf('Tasks for %s changed!', $date->format('Y-m-d')), $token, $data);
        }

        $socketIoManager
            ->toUser($user, 'tasks:changed', $normalizedTasks);
    }

    protected function getResourceFromIri($iri)
    {
        $baseContext = $this->get('router')->getContext();

        $request = Request::create($iri);
        $context = (new RequestContext())->fromRequest($request);
        $context->setMethod('GET');
        $context->setPathInfo($iri);
        $context->setScheme($baseContext->getScheme());

        try {

            $this->get('router')->setContext($context);
            $parameters = $this->get('router')->match($request->getPathInfo());

            // return $this->get('api_platform.item_data_provider')
            //     ->getItem($parameters['_api_resource_class'], $parameters['id']);

            return $this->getDoctrine()
                ->getRepository($parameters['_api_resource_class'])
                ->find($parameters['id']);

        } catch (\Exception $e) {

        } finally {
            $this->get('router')->setContext($baseContext);
        }
    }

    /**
     * @Route("/admin/dashboard", name="admin_dashboard")
     */
    public function dashboardAction(Request $request)
    {
        return $this->render('@App/admin/dashboard.html.twig', ['date' => new \DateTime()]);
    }

    /**
     * @Route("/admin/dashboard/fullscreen/{date}", name="admin_dashboard_fullscreen",
     *   requirements={"date"="[0-9]{4}-[0-9]{2}-[0-9]{2}|__DATE__"})
     */
    public function dashboardFullscreenAction($date, Request $request, TaskManager $taskManager)
    {
        $date = new \DateTime($date);
        $dayAfter = clone $date;
        $dayAfter->modify('+1 day');
        $dayBefore = clone $date;
        $dayBefore->modify('-1 day');

        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $taskImport = new \stdClass();
        $taskImport->tasks = [];

        $taskUploadForm = $this->createForm(TaskUploadType::class, $taskImport, [
            'date' => $date
        ]);

        $task = $this->createDefaultTask($date);

        $newTaskForm = $this->createForm(TaskType::class, $task, [
            'date_range' => true
        ]);

        $taskExport = new \stdClass();
        $taskExport->date = $date;
        $taskExport->csv = '';

        $taskExportForm = $this->createForm(TaskExportType::class, $taskExport);

        $taskGroupForm = $this->createForm(TaskGroupType::class);

        $taskUploadForm->handleRequest($request);
        if ($taskUploadForm->isSubmitted()) {
            if ($taskUploadForm->isValid()) {

                $taskImport = $taskUploadForm->getData();

                $taskGroup = new TaskGroup();
                $taskGroup->setName(sprintf('Import %s', date('d/m H:i')));

                $this->getDoctrine()
                    ->getManagerForClass(TaskGroup::class)
                    ->persist($taskGroup);

                foreach ($taskImport->tasks as $task) {
                    $task->setGroup($taskGroup);

                    $this->getDoctrine()
                        ->getManagerForClass(Task::class)
                        ->persist($task);
                }

                $this->getDoctrine()
                    ->getManagerForClass(Task::class)
                    ->flush();

                return $this->redirectToDashboard($request);
            }
        }

        $newTaskForm->handleRequest($request);
        if ($newTaskForm->isSubmitted() && $newTaskForm->isValid()) {

            $task = $newTaskForm->getData();

            $this->getDoctrine()
                ->getManagerForClass(Task::class)
                ->persist($task);

            $this->getDoctrine()
                ->getManagerForClass(Task::class)
                ->flush();

            return $this->redirectToDashboard($request);
        }

        $taskExportForm->handleRequest($request);
        if ($taskExportForm->isSubmitted() && $taskExportForm->isValid()) {

            $taskExport = $taskExportForm->getData();
            $filename = sprintf('tasks-%s.csv', $date->format('Y-m-d'));

            $response = new Response($taskExport->csv);
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            ));

            return $response;
        }

        $taskGroupForm->handleRequest($request);
        if ($taskGroupForm->isSubmitted() && $taskGroupForm->isValid()) {

            if ($taskGroupForm->getClickedButton() && 'delete' === $taskGroupForm->getClickedButton()->getName()) {

                $taskGroup = $this->getDoctrine()
                    ->getRepository(TaskGroup::class)
                    ->find($taskGroupForm->get('id')->getData());

                $taskManager->deleteGroup($taskGroup);

                $this->getDoctrine()
                    ->getManagerForClass(TaskGroup::class)
                    ->flush();
            }

            return $this->redirectToDashboard($request);
        }

        $allTasks = $this->getDoctrine()
            ->getRepository(Task::class)
            ->findByDate($date);

        $taskLists = $this->getDoctrine()
            ->getRepository(TaskList::class)
            ->findByDate($date);

        $allTasksNormalized = array_map(function (Task $task) {
            return $this->get('api_platform.serializer')->normalize($task, 'jsonld', [
                'resource_class' => Task::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task', 'delivery', 'place']
            ]);
        }, $allTasks);

        $taskListsNormalized = array_map(function (TaskList $taskList) {
            return $this->get('api_platform.serializer')->normalize($taskList, 'jsonld', [
                'resource_class' => TaskList::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task_collection', 'task', 'delivery', 'place']
            ]);
        }, $taskLists);

        $couriers = $this->getDoctrine()
            ->getRepository(ApiUser::class)
            ->createQueryBuilder('u')
            ->select("u.username")
            ->where('u.roles LIKE :roles')
            ->orderBy('u.username', 'ASC')
            ->setParameter('roles', '%ROLE_COURIER%')
            ->getQuery()
            ->getResult();

        $allTags = $this->getDoctrine()
            ->getRepository(Tag::class)
            ->findAll();

        $normalizedTags = [];
        foreach ($allTags as $tag) {
            $normalizedTags[] = [
                'name' => $tag->getName(),
                'slug' => $tag->getSlug(),
                'color' => $tag->getColor(),
            ];
        }

        $jwtManager = $this->container->get('lexik_jwt_authentication.jwt_manager');

        return $this->render('@App/admin/dashboard_iframe.html.twig', [
            'nav' => $request->query->getBoolean('nav', true),
            'date' => $date,
            'dayAfter' => $dayAfter,
            'dayBefore' => $dayBefore,
            'couriers' => $couriers,
            'tasks' => $allTasksNormalized,
            'task_lists' => $taskListsNormalized,
            'task_upload_form' => $taskUploadForm->createView(),
            'task_export_form' => $taskExportForm->createView(),
            'new_task_form' => $newTaskForm->createView(),
            'task_group_form' => $taskGroupForm->createView(),
            'tags' => $normalizedTags,
            'jwt' => $jwtManager->create($this->getUser()),
        ]);
    }

    protected function getTaskList(\DateTime $date, UserInterface $user)
    {
        $taskList = $this->getDoctrine()
            ->getRepository(TaskList::class)
            ->findOneBy(['date' => $date, 'courier' => $user]);

        if (null === $taskList) {
            $taskList = new TaskList();
            $taskList->setDate($date);
            $taskList->setCourier($user);
        }

        return $taskList;
    }

    /**
     * @Route("/admin/tasks/{date}/{username}", name="admin_task_list_modify",
     *   methods={"PUT"},
     *   requirements={"date"="[0-9]{4}-[0-9]{2}-[0-9]{2}"})
     */
    public function modifyTaskListAction(
        $date,
        $username,
        Request $request,
        RemotePushNotificationManager $remotePushNotificationManager,
        SocketIoManager $socketIoManager)
    {
        $date = new \DateTime($date);
        $user = $this->get('fos_user.user_manager')->findUserByUsername($username);

        $taskList = $this->getTaskList($date, $user);

        if (null === $taskList->getId()) {
            $this->getDoctrine()
                ->getManagerForClass(TaskList::class)
                ->persist($taskList);
        }

        // Tasks are sent as JSON payload
        $data = json_decode($request->getContent(), true);

        $tasksToAssign = [];
        foreach ($data as $item) {
            $tasksToAssign[$item['position']] = $this->getResourceFromIri($item['task']);
        }

        $taskList->setTasks($tasksToAssign);

        $this->getDoctrine()
            ->getManagerForClass(TaskList::class)
            ->flush();

        $taskListNormalized = $this->get('api_platform.serializer')->normalize($taskList, 'jsonld', [
            'resource_class' => TaskList::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task_collection', 'task', 'delivery', 'place']
        ]);

        $this->notifyTasksChanged(
            $user,
            $date,
            $taskListNormalized['items'],
            $remotePushNotificationManager,
            $socketIoManager
        );

        return new JsonResponse($taskListNormalized);
    }

    /**
     * @Route("/admin/task-lists/{date}/{username}", name="admin_task_list_create",
     *   methods={"POST"},
     *   requirements={"date"="[0-9]{4}-[0-9]{2}-[0-9]{2}"})
     */
    public function createTaskListAction($date, $username, Request $request)
    {
        $date = new \DateTime($date);
        $user = $this->get('fos_user.user_manager')->findUserByUsername($username);

        $taskList = $this->getTaskList($date, $user);

        if (null === $taskList->getId()) {
            $this->getDoctrine()
                ->getManagerForClass(TaskList::class)
                ->persist($taskList);
            $this->getDoctrine()
                ->getManagerForClass(TaskList::class)
                ->flush();
        }

        $taskListNormalized = $this->get('api_platform.serializer')->normalize($taskList, 'jsonld', [
            'resource_class' => TaskList::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task_collection', 'task']
        ]);

        return new JsonResponse($taskListNormalized);
    }
}
