<?php

namespace AppBundle\Controller\Utils;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\User;
use AppBundle\Entity\RemotePushToken;
use AppBundle\Entity\Store;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Task\RecurrenceRule as TaskRecurrenceRule;
use AppBundle\Form\TaskExportType;
use AppBundle\Form\TaskGroupType;
use AppBundle\Form\TaskUploadType;
use AppBundle\Service\TaskManager;
use AppBundle\Utils\TaskImageNamer;
use Cocur\Slugify\SlugifyInterface;
use Nucleos\UserBundle\Model\UserInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManagerInterface;
use phpcent\Client as CentrifugoClient;
use Psr\Log\LoggerInterface;
use Redis;
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
            'date' => $request->get('date', (new \DateTime())->format('Y-m-d')),
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
        JWTManagerInterface $jwtManager,
        CentrifugoClient $centrifugoClient,
        Redis $tile38)
    {
        return $this->dashboardFullscreenAction((new \DateTime())->format('Y-m-d'),
            $request, $taskManager, $jwtManager, $centrifugoClient, $tile38);
    }

    /**
     * @Route("/admin/dashboard/fullscreen/{date}", name="admin_dashboard_fullscreen",
     *   requirements={"date"="[0-9]{4}-[0-9]{2}-[0-9]{2}"})
     */
    public function dashboardFullscreenAction($date, Request $request,
        TaskManager $taskManager,
        JWTManagerInterface $jwtManager,
        CentrifugoClient $centrifugoClient,
        Redis $tile38)
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

        $unassignedTasks = $this->getDoctrine()
            ->getRepository(Task::class)
            ->findUnassignedByDate($date);

        $taskLists = $this->getDoctrine()
            ->getRepository(TaskList::class)
            ->findByDate($date);

        $unassignedTasksNormalized = array_map(function (Task $task) {
            return $this->get('serializer')->normalize($task, 'jsonld', [
                'resource_class' => Task::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task', 'delivery', 'address', sprintf('address_%s', $this->getParameter('country_iso'))]
            ]);
        }, $unassignedTasks);

        $taskListsNormalized = array_map(function (TaskList $taskList) {
            return $this->get('serializer')->normalize($taskList, 'jsonld', [
                'resource_class' => TaskList::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task_collection', 'task', 'delivery', 'address', sprintf('address_%s', $this->getParameter('country_iso'))]
            ]);
        }, $taskLists);

        $couriers = $this->getDoctrine()
            ->getRepository(User::class)
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

        $positions = $this->loadPositions($tile38);

        $this->getDoctrine()->getManager()->getFilters()->enable('soft_deleteable');

        $recurrenceRules =
            $this->getDoctrine()->getRepository(TaskRecurrenceRule::class)->findAll();

        $recurrenceRulesNormalized = array_map(function (TaskRecurrenceRule $recurrenceRule) {
            return $this->get('serializer')->normalize($recurrenceRule, 'jsonld', [
                'resource_class' => TaskRecurrenceRule::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
            ]);
        }, $recurrenceRules);

        $this->getDoctrine()->getManager()->getFilters()->disable('soft_deleteable');

        $stores = $this->getDoctrine()->getRepository(Store::class)->findBy([], ['name' => 'ASC']);

        $storesNormalized = array_map(function (Store $store) {
            return $this->get('serializer')->normalize($store, 'jsonld', [
                'resource_class' => Store::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
            ]);
        }, $stores);

        $restaurants = $this->getDoctrine()->getRepository(LocalBusiness::class)->findBy([], ['name' => 'ASC']);

        $restaurantsNormalized = array_map(function (LocalBusiness $restaurant) {
            return $this->get('serializer')->normalize($restaurant, 'jsonld', [
                'resource_class' => LocalBusiness::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['restaurant_simple']
            ]);
        }, $restaurants);

        return $this->render('admin/dashboard_iframe.html.twig', [
            'nav' => $request->query->getBoolean('nav', true),
            'date' => $date,
            'couriers' => $couriers,
            'unassigned_tasks' => $unassignedTasksNormalized,
            'task_lists' => $taskListsNormalized,
            'task_export_form' => $taskExportForm->createView(),
            'tags' => $normalizedTags,
            'jwt' => $jwtManager->create($this->getUser()),
            'centrifugo_token' => $centrifugoClient->generateConnectionToken($this->getUser()->getUsername(), (time() + 3600)),
            'centrifugo_tracking_channel' => sprintf('$%s_tracking', $this->getParameter('centrifugo_namespace')),
            'centrifugo_events_channel' => sprintf('%s_events#%s', $this->getParameter('centrifugo_namespace'), $this->getUser()->getUsername()),
            'positions' => $positions,
            'task_recurrence_rules' => $recurrenceRulesNormalized,
            'stores' => $storesNormalized,
            'restaurants' => $restaurantsNormalized,
        ]);
    }

    private function loadPositions(Redis $tile38, $cursor = 0, array $points = [])
    {
        $result = $tile38->rawCommand(
            'SCAN',
            $this->getParameter('tile38_fleet_key'),
            'CURSOR',
            $cursor,
            'LIMIT',
            '10'
        );

        $newCursor = $result[0];
        $objects = $result[1];

        // Remember: more or less than COUNT or no keys may be returned
        // See http://redis.io/commands/scan#the-count-option
        // Also, SCAN may return the same key multiple times
        // See http://redis.io/commands/scan#scan-guarantees
        // Additionally, you should always have the code that uses the keys
        // before the code checking the cursor.
        if (count($objects) > 0) {
            foreach ($objects as $object) {
                [$username, $data] = $object;
                $point = json_decode($data, true);
                // Warning: format is lng,lat
                [$longitude, $latitude, $timestamp] = $point['coordinates'];

                $points[] = [
                    'username' => $username,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timestamp' => $timestamp,
                ];
            }
        }

        // It's important to note that the cursor and returned keys
        // vary independently. The scan is never complete until redis
        // returns a non-zero cursor. However, with MATCH and large
        // collections, most iterations will return an empty keys array.

        // Still, a cursor of zero DOES NOT mean that there are no keys.
        // A zero cursor just means that the SCAN is complete, but there
        // might be one last batch of results to process.

        // From <http://redis.io/commands/scan>:
        // 'An iteration starts when the cursor is set to 0,
        // and terminates when the cursor returned by the server is 0.'
        if ($newCursor === 0) {
            return $points;
        }

        return $this->loadPositions($tile38, $newCursor, $points);
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
    public function downloadTaskImageAction($taskId, $imageId,
        StorageInterface $storage,
        SlugifyInterface $slugify,
        Filesystem $taskImagesFilesystem)
    {
        $image = $this->getDoctrine()->getRepository(TaskImage::class)->find($imageId);

        if (!$image) {
            throw new NotFoundHttpException(sprintf('Image #%d not found', $imageId));
        }

        // @see https://symfonycasts.com/screencast/symfony-uploads/file-streaming

        // FIXME
        // It's not clean to use resolveUri()
        // but the problem is that resolvePath() returns the path with prefix,
        // while $taskImagesFilesystem is alreay aware of the prefix
        $imagePath = ltrim($storage->resolveUri($image, 'file'), '/');

        if (!$taskImagesFilesystem->has($imagePath)) {
            throw new NotFoundHttpException(sprintf('Image at path "%s" not found', $imagePath));
        }

        $response = new StreamedResponse(function() use ($storage, $image) {
            $outputStream = fopen('php://output', 'wb');
            $fileStream = $storage->resolveStream($image, 'file');
            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $taskImagesFilesystem->getMimetype($imagePath));

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
