<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Form\TaskExportType;
use AppBundle\Form\TaskGroupType;
use AppBundle\Form\TaskType;
use AppBundle\Form\TaskUploadType;
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

    protected function publishTasksChangedEvent(array $normalizedTasks, UserInterface $user)
    {
        $normalizedUser = $this->get('serializer')->normalize($user, 'jsonld', [
            'resource_class' => ApiUser::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get'
        ]);

        $this->get('snc_redis.default')->publish('tasks:changed', json_encode([
            'tasks' => $normalizedTasks,
            'user' => $normalizedUser
        ]));
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
        return $this->render('@App/Admin/dashboard.html.twig', ['date' => new \DateTime()]);
    }

    /**
     * @Route("/admin/dashboard/fullscreen/{date}", name="admin_dashboard_fullscreen",
     *   requirements={"date"="[0-9]{4}-[0-9]{2}-[0-9]{2}|__DATE__"})
     */
    public function dashboardFullscreenAction($date, Request $request)
    {
        $date = new \DateTime($date);

        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $taskImport = new \stdClass();
        $taskImport->tasks = [];

        $taskUploadForm = $this->createForm(TaskUploadType::class, $taskImport, [
            'date' => $date
        ]);

        $task = $this->createDefaultTask($date);

        $newTaskForm = $this->createForm(TaskType::class, $task);

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

                $tasks = $this->getDoctrine()
                    ->getRepository(Task::class)
                    ->findByGroup($taskGroup);

                $deleteGroup = true;
                foreach ($tasks as $task) {
                    if (!$task->isAssigned()) {
                        $this->getDoctrine()
                            ->getManagerForClass(Task::class)
                            ->remove($task);
                    } else {
                        $deleteGroup = false;
                    }
                }

                if ($deleteGroup) {
                    $this->getDoctrine()
                        ->getManagerForClass(TaskGroup::class)
                        ->remove($taskGroup);
                }

                $this->getDoctrine()
                    ->getManagerForClass(Task::class)
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

        return $this->render('@App/Admin/dashboardIframe.html.twig', [
            'nav' => $request->query->getBoolean('nav', true),
            'date' => $date,
            'couriers' => $couriers,
            'tasks' => $allTasksNormalized,
            'task_lists' => $taskListsNormalized,
            'task_upload_form' => $taskUploadForm->createView(),
            'task_export_form' => $taskExportForm->createView(),
            'new_task_form' => $newTaskForm->createView(),
            'task_group_form' => $taskGroupForm->createView(),
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
    public function modifyTaskListAction($date, $username, Request $request)
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

        $assignedTasks = new \SplObjectStorage();
        foreach ($taskList->getItems() as $taskListItem) {
            $assignedTasks[$taskListItem->getTask()] = $taskListItem->getPosition();
        }

        $tasksToAssign = new \SplObjectStorage();
        foreach ($data as $item) {
            $task = $this->getResourceFromIri($item['task']);
            $tasksToAssign[$task] = $item['position'];
        }

        $tasksToUnassign = [];
        foreach ($assignedTasks as $task) {
            if (!$tasksToAssign->contains($task)) {
                $tasksToUnassign[] = $task;
            }
        }

        foreach ($tasksToUnassign as $task) {
            $taskList->removeTask($task);
        }
        foreach ($tasksToAssign as $task) {
            $taskList->addTask($task, $tasksToAssign[$task]);
        }

        $this->getDoctrine()
            ->getManagerForClass(TaskList::class)
            ->flush();

        // Publish a Redis event in task:changed channel
        $tasksNormalized = $this->get('api_platform.serializer')->normalize($taskList->getTasks(), 'jsonld', [
            'resource_class' => Task::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task', 'delivery', 'place']
        ]);

        $this->publishTasksChangedEvent($tasksNormalized, $user);

        $taskListNormalized = $this->get('api_platform.serializer')->normalize($taskList, 'jsonld', [
            'resource_class' => TaskList::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task_collection', 'task', 'delivery', 'place']
        ]);

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
