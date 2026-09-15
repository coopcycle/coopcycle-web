<?php

namespace AppBundle\EventListener\Edifact;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use Doctrine\ORM\EntityManagerInterface;
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
 * happens last — the app uploads its images after marking the task as done, so
 * either can be the trigger. Proofs that arrive later produce a further POD|CFM
 * with only the images that have not been reported yet.
 */
class TransporterPodNotifier {

    private const SUB_MESSAGE_TYPE = 'POD|CFM';

    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $transporterLogger,
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
        $this->onImageAttached($image);
    }

    private function onImageAttached(TaskImage $image): void
    {
        if (!is_null($image->getTask())) {
            $this->notify($image->getTask());
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

        $ediMessage = new EDIFACTMessage();
        $ediMessage->setMessageType(EDIFACTMessage::MESSAGE_TYPE_REPORT);
        $ediMessage->setSubMessageType(self::SUB_MESSAGE_TYPE);
        $ediMessage->setTransporter($importMessage->getTransporter());
        $ediMessage->setDirection(EDIFACTMessage::DIRECTION_OUTBOUND);
        $ediMessage->setReference($importMessage->getReference());
        $ediMessage->setPods($pods);

        $task->addEdifactMessage($ediMessage);
        $this->em->persist($ediMessage);
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

        // Queried rather than read off $task->getImages(): CreateImage attaches
        // an image by setting the owning side only, so the task's collection
        // may not have it, and may already be initialized without it.
        /** @var array<int,TaskImage> $images */
        $images = $this->em->getRepository(TaskImage::class)->findBy(['task' => $task]);

        $pending = array_filter(
            $images,
            fn(TaskImage $i) => !in_array($i->getImageName(), $reported, true)
        );

        return array_values(array_map(
            fn(TaskImage $i) => $this->urlGenerator->generate(
                'task_image_public',
                ['path' => $i->getImageName()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            $pending
        ));
    }
}
