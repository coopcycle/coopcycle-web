<?php

declare(strict_types=1);

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\ExceptionInterface as ApiPlatformException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\CancelTasksDto;
use AppBundle\Entity\Task;
use AppBundle\Service\TaskManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use Doctrine\ORM\EntityManagerInterface;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CancelTasksProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TaskManager $taskManager,
        private readonly IriConverterInterface $iriConverter,
        private readonly EntityManagerInterface $entityManager,
        private readonly NormalizerInterface $normalizer,
        private readonly StateMachineFactoryInterface $stateMachineFactory,
        private readonly TranslatorInterface $translator,
    )
    {}

    /**
     * @param CancelTasksDto $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $tasks = [];
        $cancelled = [];
        $failed = [];

        // A task that was deleted in the meantime doesn't prevent the others to be cancelled
        foreach ($data->tasks as $iri) {
            try {
                $task = $this->iriConverter->getResourceFromIri($iri);
            } catch (ApiPlatformException $e) {
                $failed[$iri] = $e->getMessage();
                continue;
            }

            if (!$task instanceof Task) {
                $failed[$iri] = sprintf('"%s" is not a task', $iri);
                continue;
            }

            if (!$task->isCancelled()) {
                $tasks[] = $task;
            }
        }

        foreach ($this->groupByDelivery($tasks) as $group) {

            $error = $this->getCancellationError($group);
            if (!is_null($error)) {
                foreach ($group as $task) {
                    $failed[$this->iriConverter->getIriFromResource($task)] = $error;
                }
                continue;
            }

            // The tasks of a delivery are cancelled with a single command,
            // so that when all of them are cancelled, the order is cancelled
            // without recalculating its price for each task
            try {
                $this->taskManager->cancelTasks($group, recalculatePrice: true);
            } catch (HandlerFailedException $e) {
                // i.e the order state changed since it was checked; the events of the failed command are discarded,
                // so revert the tasks too, to flush the other groups only
                $cause = $e;
                while ($cause instanceof HandlerFailedException && null !== $cause->getPrevious()) {
                    $cause = $cause->getPrevious();
                }
                foreach ($group as $task) {
                    $this->entityManager->refresh($task);
                    $failed[$this->iriConverter->getIriFromResource($task)] = $cause->getMessage();
                }
                continue;
            }

            $cancelled = array_merge($cancelled, $group);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => $this->normalizer->normalize($cancelled, 'jsonld', ['groups' => ['task', 'delivery', 'address']]),
            'failed' => $failed,
        ], 200);
    }

    /**
     * @param Task[] $tasks
     * @return array<string,Task[]>
     */
    private function groupByDelivery(array $tasks): array
    {
        $groups = [];
        foreach ($tasks as $task) {
            $key = is_null($task->getDelivery()) ?
                sprintf('task_%d', $task->getId()) : sprintf('delivery_%d', $task->getDelivery()->getId());

            $groups[$key][] = $task;
        }

        return $groups;
    }

    /**
     * When all the remaining tasks of a delivery are cancelled, its order is cancelled too (see CancelHandler).
     * Detects the orders that can't be cancelled (i.e still in cart, or fulfilled) before changing anything.
     *
     * @param Task[] $tasks
     */
    private function getCancellationError(array $tasks): ?string
    {
        $delivery = current($tasks)->getDelivery();
        if (is_null($delivery)) {
            return null;
        }

        $order = $delivery->getOrder();
        if (is_null($order)) {
            return null;
        }

        $remaining = array_filter(
            $delivery->getTasks('not task.isCancelled()'),
            fn(Task $task) => !in_array($task, $tasks, true)
        );

        if (count($remaining) > 0) {
            return null;
        }

        if (in_array($order->getState(), [OrderInterface::STATE_CANCELLED, OrderInterface::STATE_REFUSED])) {
            return null;
        }

        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);
        if ($stateMachine->can(OrderTransitions::TRANSITION_CANCEL)) {
            return null;
        }

        return $this->translator->trans('task.cancel.order_not_cancellable', [
            '%number%' => $order->getNumber() ?? $order->getId(),
        ]);
    }
}
