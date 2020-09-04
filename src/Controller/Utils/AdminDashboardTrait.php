<?php

namespace AppBundle\Controller\Utils;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\RemotePushToken;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Form\TaskExportType;
use AppBundle\Form\TaskGroupType;
use AppBundle\Form\TaskUploadType;
use AppBundle\Service\TaskManager;
use AppBundle\Utils\TaskImageNamer;
use Cocur\Slugify\SlugifyInterface;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\FileinfoMimeTypeGuesser;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

trait AdminDashboardTrait
{
    protected function redirectToDashboard(Request $request, array $params = [])
    {
        $nav = $request->query->getBoolean('nav', true);

        $defaultParams = [
            'date' => $request->get('date'),
        ];

        if (!$nav) {
            $defaultParams['nav'] = 'off';
        }

        return $this->redirectToRoute('admin_dashboard_fullscreen', array_merge($defaultParams, $params));
    }

    /**
     * @Route("/admin/dashboard", name="admin_dashboard")
     */
    public function dashboardAction(Request $request,
        TaskManager $taskManager,
        JWTManagerInterface $jwtManager)
    {
        return $this->dashboardFullscreenAction((new \DateTime())->format('Y-m-d'),
            $request, $taskManager, $jwtManager);
    }

    /**
     * @Route("/admin/dashboard/fullscreen/{date}", name="admin_dashboard_fullscreen",
     *   requirements={"date"="[0-9]{4}-[0-9]{2}-[0-9]{2}"})
     */
    public function dashboardFullscreenAction($date, Request $request,
        TaskManager $taskManager,
        JWTManagerInterface $jwtManager)
    {
        $hashids = new Hashids($this->getParameter('secret'), 8);

        $date = new \DateTime($date);

        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $taskExport = new \stdClass();
        $taskExport->start = new \DateTime('first day of this month');
        $taskExport->end = new \DateTime();

        $taskExportForm = $this->createForm(TaskExportType::class, $taskExport);
        $taskGroupForm = $this->createForm(TaskGroupType::class);

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
            return $this->get('serializer')->normalize($task, 'jsonld', [
                'resource_class' => Task::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task', 'delivery', 'address', sprintf('address_%s', $this->getParameter('country_iso'))]
            ]);
        }, $allTasks);

        $taskListsNormalized = array_map(function (TaskList $taskList) {
            return $this->get('serializer')->normalize($taskList, 'jsonld', [
                'resource_class' => TaskList::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task_collection', 'task', 'delivery', 'address', sprintf('address_%s', $this->getParameter('country_iso'))]
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

        return $this->render('admin/dashboard_iframe.html.twig', [
            'nav' => $request->query->getBoolean('nav', true),
            'date' => $date,
            'couriers' => $couriers,
            'tasks' => $allTasksNormalized,
            'task_lists' => $taskListsNormalized,
            'task_export_form' => $taskExportForm->createView(),
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
    public function modifyTaskListAction($date, $username, Request $request,
        IriConverterInterface $iriConverter,
        UserManagerInterface $userManager,
        LoggerInterface $logger)
    {
        $date = new \DateTime($date);
        $user = $userManager->findUserByUsername($username);

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
            // Sometimes $item['task'] is "/api/tasks/"
            // @see https://github.com/coopcycle/coopcycle-web/issues/976
            try {
                $tasksToAssign[$item['position']] = $iriConverter->getItemFromIri($item['task']);
            } catch (InvalidArgumentException $e) {
                $logger->error($e->getMessage());
            }
        }

        $taskList->setTasks($tasksToAssign);

        $this->getDoctrine()
            ->getManagerForClass(TaskList::class)
            ->flush();

        return new JsonResponse($this->get('serializer')->normalize($taskList, 'jsonld', [
            'resource_class' => TaskList::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task_collection', 'task', 'delivery', 'address']
        ]));
    }

    /**
     * @Route("/admin/task-lists/{date}/{username}", name="admin_task_list_create",
     *   methods={"POST"},
     *   requirements={"date"="[0-9]{4}-[0-9]{2}-[0-9]{2}"})
     */
    public function createTaskListAction($date, $username, Request $request, UserManagerInterface $userManager)
    {
        $date = new \DateTime($date);
        $user = $userManager->findUserByUsername($username);

        $taskList = $this->getTaskList($date, $user);

        if (null === $taskList->getId()) {
            $this->getDoctrine()
                ->getManagerForClass(TaskList::class)
                ->persist($taskList);
            $this->getDoctrine()
                ->getManagerForClass(TaskList::class)
                ->flush();
        }

        $taskListNormalized = $this->get('serializer')->normalize($taskList, 'jsonld', [
            'resource_class' => TaskList::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task_collection', 'task']
        ]);

        return new JsonResponse($taskListNormalized);
    }

    /**
     * @Route("/admin/tasks/{taskId}/images/{imageId}/download", name="admin_task_image_download")
     */
    public function downloadTaskImage($taskId, $imageId, StorageInterface $storage, SlugifyInterface $slugify)
    {
        $image = $this->getDoctrine()->getRepository(TaskImage::class)->find($imageId);

        if (!$image) {
            throw new NotFoundHttpException(sprintf('Image #%d not found', $imageId));
        }

        // @see https://symfonycasts.com/screencast/symfony-uploads/file-streaming

        // FIXME
        // It's not clean to use resolveUri()
        // but the problem is that resolvePath() returns the path with prefix,
        // while $fs is alreay aware of the prefix
        $imagePath = ltrim($storage->resolveUri($image, 'file'), '/');

        $fs = $this->get('task_images_filesystem');

        if (!$fs->has($imagePath)) {
            throw new NotFoundHttpException(sprintf('Image at path "%s" not found', $imagePath));
        }

        $response = new StreamedResponse(function() use ($storage, $image) {
            $outputStream = fopen('php://output', 'wb');
            $fileStream = $storage->resolveStream($image, 'file');
            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $fs->getMimetype($imagePath));

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->getImageDownloadFileName($image, $slugify)
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    protected function getImageDownloadFileName(TaskImage $taskImage, SlugifyInterface $slugify)
    {
        $taskImageNamer = new TaskImageNamer($slugify);

        return $taskImageNamer->getImageDownloadFileName($taskImage);
    }
}
