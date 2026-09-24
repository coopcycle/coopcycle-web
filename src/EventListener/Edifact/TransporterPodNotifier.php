<?php

namespace AppBundle\EventListener\Edifact;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Emits the POD|CFM event.
 *
 * Per the REPORT 3.1 specification, POD|CFM is the only status that attests the
 * receipt image is available: a COM URL carried on any other status (LIV|CFM
 * included) is treated as provisional and never closes the position.
 *
 * The event is emitted once the dropoff is done *and* carries a proof, whichever
 * happens last: the images may be uploaded before or after the task is marked
 * as done, so either can be the trigger. Proofs that arrive later produce a
 * further POD|CFM with only the images that have not been reported yet; the
 * ones still unsynced are sent as one event, see ReportFromCC::generateReports().
 */
class TransporterPodNotifier {

    public const SUB_MESSAGE_TYPE = 'POD|CFM';

    // REPORT 3.1 allows at most 9 COM segments per RSJ.
    public const MAX_PODS = 9;

    /** @var array<int,Task> */
    private array $tasksToNotify = [];

    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $transporterLogger,
        private string $baseUrl,
    ) { }

    // Covers CreateImage's X-Attach-To path, where the image is persisted with
    // its task already set.
    public function postPersist(TaskImage $image, PostPersistEventArgs $event): void
    {
        $this->onImageAttached($image);
    }

    // Covers AddImagesToTasks, which links an already uploaded image later on.
    public function postUpdate(TaskImage $image, PostUpdateEventArgs $event): void
    {
        $changeset = $event->getObjectManager()->getUnitOfWork()->getEntityChangeSet($image);
        if (array_key_exists('task', $changeset)) {
            $this->onImageAttached($image);
        }
    }

    private function onImageAttached(TaskImage $image): void
    {
        if (!is_null($image->getTask())) {
            $this->tasksToNotify[spl_object_id($image->getTask())] = $image->getTask();
        }
    }

    // Deferred until the whole flush is written: Doctrine fires postUpdate row
    // by row, so notifying from there would only see the images updated so far
    // and emit one POD|CFM per image.
    public function postFlush(PostFlushEventArgs $event): void
    {
        $tasks = $this->tasksToNotify;
        $this->tasksToNotify = [];

        // The images are already committed: a failure here must not turn the
        // courier's upload into an error.
        foreach ($tasks as $task) {
            try {
                $this->notify($task);
            } catch (\Throwable $e) {
                $this->transporterLogger->error(
                    sprintf('Could not schedule a POD|CFM report for task "%s": %s', $task->getId(), $e->getMessage()),
                    ['exception' => $e]
                );
            }
        }
    }

    public function notify(Task $task): void
    {
        // isDone(), not isCompleted(): the latter is true for FAILED too, and a
        // failed delivery has no proof of delivery.
        if (!$task->isDropoff() || !$task->isDone()) {
            return;
        }

        $importMessage = $task->getImportMessage();
        if (is_null($importMessage)) {
            return;
        }

        $pods = $this->pendingPods($task);
        if (empty($pods)) {
            return;
        }

        foreach (array_chunk($pods, self::MAX_PODS) as $chunk) {
            $ediMessage = EDIFACTMessage::createReport($importMessage, self::SUB_MESSAGE_TYPE);
            $ediMessage->setPods($chunk);

            $task->addEdifactMessage($ediMessage);
            $this->em->persist($ediMessage);
        }
        $this->em->persist($task);
        $this->em->flush();

        $this->transporterLogger->info(
            sprintf(
                'Scheduled a POD|CFM report for task "%s" with %d proof(s)',
                $task->getId(), count($pods)
            ),
            ['transporter' => $importMessage->getTransporter()]
        );
    }

    /**
     * URLs of the proofs on this task that no POD|CFM has reported yet.
     *
     * The comparison is made on the image name rather than on the stored URL:
     * the URL is built from router.request_context.*, so a change of host or
     * scheme would make every past proof look unreported and send it again.
     *
     * @return array<int,string>
     */
    private function pendingPods(Task $task): array
    {
        $reported = $task->getReports()
            ->filter(fn(EDIFACTMessage $m) => $m->getSubMessageType() === self::SUB_MESSAGE_TYPE)
            ->flatMap(fn(EDIFACTMessage $m) => $m->getPods())
            ->map(fn(string $url) => basename((string) parse_url($url, PHP_URL_PATH)))
            ->all();

        return $this->podUrls($task, $reported);
    }

    /**
     * URLs of the proofs on this task, but the ones named in $except.
     *
     * @param array<int,string> $except image names
     * @return array<int,string>
     */
    public function podUrls(Task $task, array $except = []): array
    {
        // Queried rather than read off $task->getImages(): CreateImage attaches
        // an image by setting the owning side only, so the task's collection
        // may not have it, and may already be initialized without it.
        /** @var array<int,TaskImage> $images */
        $images = $this->em->getRepository(TaskImage::class)->findBy(['task' => $task]);

        // Unique on the name: the same file can be linked twice to one task.
        $pending = array_unique(array_filter(
            array_map(fn(TaskImage $i) => $i->getImageName(), $images),
            fn(string $imageName) => !in_array($imageName, $except, true)
        ));

        // Prefixed with the canonical base URL rather than ABSOLUTE_URL: in an
        // HTTP request the latter uses whatever host the app called us on.
        return array_values(array_map(
            fn(string $imageName) => $this->baseUrl . $this->urlGenerator->generate(
                'task_image_public',
                ['path' => $imageName],
                UrlGeneratorInterface::ABSOLUTE_PATH
            ),
            $pending
        ));
    }
}
