<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Resource\CykeWebhook;
use AppBundle\Entity\Cyke\Delivery as CykeDelivery;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CykeWebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskManager $taskManager,
        private RequestStack $requestStack,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger)
    {}

    /**
     * @param CykeWebhook $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!CykeWebhook::isValidEvent((string) $data->eventType)) {
            $this->logger->warning(sprintf('Unknown Cyke webhook event type "%s"', $data->eventType));

            return $data;
        }

        $deliveryPayload = $data->payload['delivery'] ?? null;

        if (!is_array($deliveryPayload) || !isset($deliveryPayload['id'])) {
            throw new NotFoundHttpException('Missing delivery payload');
        }

        $cykeDelivery = $this->entityManager
            ->getRepository(CykeDelivery::class)
            ->findOneBy(['cykeId' => (string) $deliveryPayload['id']]);

        if (null === $cykeDelivery) {
            $this->logger->warning(
                sprintf('Received Cyke webhook for unknown delivery "%s"', $deliveryPayload['id'])
            );

            throw new NotFoundHttpException();
        }

        $delivery = $cykeDelivery->getDelivery();
        $store = $delivery->getStore();

        $providedToken = $this->requestStack->getCurrentRequest()?->headers->get('X-Cyke-Token');
        $expectedToken = $store?->getCykeWebhookSecret();

        if (empty($expectedToken) || empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            $this->logger->warning(
                sprintf('Received Cyke webhook for delivery #%d with an invalid token', $delivery->getId())
            );

            throw new AccessDeniedHttpException('Invalid webhook token');
        }

        $this->logger->info(
            sprintf('Processing Cyke webhook "%s" for delivery #%d', $data->eventType, $delivery->getId()),
            ['payload' => $deliveryPayload]
        );

        switch ($data->eventType) {
            case CykeWebhook::DELIVERY_DELIVERED:
                $this->completeIfDone($delivery->getPickup(), $deliveryPayload['pickup'] ?? []);
                $this->completeIfDone($delivery->getDropoff(), $deliveryPayload['dropoff'] ?? []);
                break;
            case CykeWebhook::DELIVERY_PICKED_UP:
                $this->completeIfDone($delivery->getPickup(), $deliveryPayload['pickup'] ?? []);
                break;
            case CykeWebhook::DELIVERY_FAILED:
                $this->failIfFailed($delivery->getPickup(), $deliveryPayload['pickup'] ?? []);
                $this->failIfFailed($delivery->getDropoff(), $deliveryPayload['dropoff'] ?? []);
                break;
            case CykeWebhook::DELIVERY_CANCELLED:
                foreach ($delivery->getTasks() as $task) {
                    if (!$task->isCompleted() && !$task->isCancelled()) {
                        $this->taskManager->cancel($task);
                    }
                }
                break;
            default:
                // delivery_saved / delivery_ready / delivery_scheduled
                // are internal Cyke states that don't require any action on our side
                break;
        }

        $this->entityManager->flush();

        return $data;
    }

    /**
     * Attaches proofs of delivery and marks the task as done,
     * mirroring what happened on the Cyke side.
     */
    private function completeIfDone(?Task $task, array $data): void
    {
        if (null === $task || empty($data['completed_at'])) {
            return;
        }

        if ($task->isCompleted() || $task->isCancelled()) {
            return;
        }

        $this->attachProofOfDelivery($task, $data);

        try {
            $this->taskManager->markAsDone(
                $task,
                $data['completion_text'] ?? null,
                $this->resolveAcceptedBy($data)
            );
        } catch (TaskAlreadyCompletedException | TaskCancelledException | PreviousTaskNotCompletedException $e) {
            // Webhooks may be delivered more than once, or out of order
            $this->logger->warning(sprintf('Could not mark task #%d as done: %s', $task->getId(), $e->getMessage()));
        }
    }

    private function failIfFailed(?Task $task, array $data): void
    {
        if (null === $task || empty($data['failure_reason'])) {
            return;
        }

        if ($task->isCompleted() || $task->isCancelled()) {
            return;
        }

        $this->attachProofOfDelivery($task, $data);

        try {
            $this->taskManager->markAsFailed(
                $task,
                $data['completion_text'] ?? null,
                $this->resolveAcceptedBy($data),
                $data['failure_reason']
            );
        } catch (TaskAlreadyCompletedException | TaskCancelledException | PreviousTaskNotCompletedException $e) {
            $this->logger->warning(sprintf('Could not mark task #%d as failed: %s', $task->getId(), $e->getMessage()));
        }
    }

    private function resolveAcceptedBy(array $data): ?string
    {
        foreach ($data['completion_pictures'] ?? [] as $picture) {
            if (is_array($picture) && !empty($picture['accepted_by'])) {
                return $picture['accepted_by'];
            }
        }

        return null;
    }

    private function attachProofOfDelivery(Task $task, array $data): void
    {
        foreach ($data['completion_pictures'] ?? [] as $picture) {
            $url = is_array($picture) ? ($picture['url'] ?? null) : $picture;
            if (!empty($url)) {
                $this->downloadAndAttachImage($task, $url);
            }
        }

        $signature = $data['completion_signature'] ?? null;
        $signatureUrl = is_array($signature) ? ($signature['url'] ?? null) : $signature;
        if (!empty($signatureUrl)) {
            $this->downloadAndAttachImage($task, $signatureUrl);
        }
    }

    private function downloadAndAttachImage(Task $task, string $url): void
    {
        try {
            $content = $this->httpClient->request('GET', $url)->getContent();
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Unable to download Cyke proof image "%s": %s', $url, $e->getMessage()));
            return;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg';
        $filename = uniqid('cyke_pod_', true) . '.' . $extension;
        $tmpPath = sprintf('%s/%s', sys_get_temp_dir(), $filename);
        file_put_contents($tmpPath, $content);

        // Vich only picks up UploadedFile/ReplacingFile instances (see UploadHandler::hasUploadedFile),
        // so a plain File silently fails to upload and imageName stays null.
        $taskImage = new TaskImage();
        $taskImage->setFile(new UploadedFile($tmpPath, $filename, null, null, true));
        $taskImage->setTask($task);

        $this->entityManager->persist($taskImage);
        $task->incrementImageCount();
    }
}
