<?php

namespace AppBundle\Action\Incident;

use ApiPlatform\Validator\Exception\ValidationException;
use AppBundle\Entity\Delivery\FailureReason;
use AppBundle\Entity\Delivery\FailureReasonRegistry;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateIncident
{
    private const DEFAULT_TITLE = 'N/A';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskManager $taskManager,
        private readonly FailureReasonRegistry $failureReasonRegistry,
        private readonly ValidatorInterface $validator,
    )
    { }

    public function findDescriptionByCode(?string $code = null): ?string
    {
        if (null === $code) {
            return self::DEFAULT_TITLE;
        }

        $defaults = $this->failureReasonRegistry->getFailureReasons();
        $defaults = array_reduce($defaults, function($carry, $failure_reason) {
            $carry[$failure_reason['code']] = $failure_reason;
            return $carry;
        }, []);

        if (array_key_exists($code, $defaults)) {
            return $defaults[$code]['description'];
        }

        $failure_reason = $this->em->getRepository(FailureReason::class)->findOneBy(['code' => $code]);
        if (!is_null($failure_reason)) {
            return $failure_reason->getDescription();
        }

        // FIXME The title field is actually NOT NULL in database
        return self::DEFAULT_TITLE;
    }

    public function __invoke(Incident $data, ?UserInterface $user, Request $request): Incident
    {
        // The default API platform validator is called on the object returned by the Controller/Action
        // but we need to validate the delivery before we can create an incident
        // @see ApiPlatform\Symfony\EventListener\ValidateListener
        $errors = $this->validator->validate($data);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $title = trim($data->getTitle() ?? '');

        if (empty($title)) {
            $data->setTitle($this->findDescriptionByCode($data->getFailureReasonCode()));
        }

        $this->preFillMissingDataInSuggestion($data);

        if (null !== $user) {
            $data->setCreatedBy($user);
        }

        $this->em->persist($data);
        $this->em->flush();

        $this->taskManager->incident(
            $data->getTask(),
            $data->getFailureReasonCode() ?? '',
            $data->getTitle(),
            [
                'incident_id' => $data->getId()
            ],
            $data
        );

        return $data;
    }

    private function preFillMissingDataInSuggestion(Incident $incident): void
    {
        $metadata = $incident->getMetadata();

        if (empty($metadata)) {
            return;
        }

        // Check if any metadata item contains a suggestion
        foreach ($metadata as $index => $item) {
            if (!is_array($item) || !isset($item['suggestion'])) {
                continue;
            }

            $suggestion = $item['suggestion'];

            $delivery = $incident->getTask()->getDelivery();
            if (null === $delivery) {
                throw new BadRequestHttpException('The task must be associated with a delivery to create an incident with a suggestion');
            }

            // Add the delivery ID to the suggestion
            $suggestion['id'] = $delivery->getId();

            // Prefill missing tasks from the original delivery;
            // this flow does not allow removing tasks

            if (!isset($suggestion['tasks']) || !is_array($suggestion['tasks'])) {
                $suggestion['tasks'] = [];
            }

            // Get all task IDs that are already in the suggestion
            $existingTaskIds = array_map(function ($task) {
                return $task['id'] ?? null;
            }, $suggestion['tasks']);
            $existingTaskIds = array_filter($existingTaskIds);

            $originalTasks = $delivery->getTasks();

            // Build a position map to maintain original task order
            $taskPositionMap = [];
            foreach ($originalTasks as $position => $originalTask) {
                $taskPositionMap[$originalTask->getId()] = $position;
            }

            // Add missing tasks from the original delivery
            foreach ($originalTasks as $originalTask) {
                $taskId = $originalTask->getId();

                if (in_array($taskId, $existingTaskIds)) {
                    continue;
                }

                $taskData = [
                    'id' => $taskId,
                ];

                $suggestion['tasks'][] = $taskData;
            }

            // Sort tasks to maintain the same order as in the original delivery
            usort($suggestion['tasks'], function ($a, $b) use ($taskPositionMap) {
                $posA = $taskPositionMap[$a['id']] ?? PHP_INT_MAX;
                $posB = $taskPositionMap[$b['id']] ?? PHP_INT_MAX;
                return $posA <=> $posB;
            });

            $metadata[$index]['suggestion'] = $suggestion;
        }

        $incident->setMetadata($metadata);
    }
}
