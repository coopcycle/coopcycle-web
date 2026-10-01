<?php

declare(strict_types=1);

namespace AppBundle\Action\Task;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Service\TaskManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderTransitions;
use Doctrine\ORM\EntityManagerInterface;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BulkCancel extends Base
{
    public function __construct(
        TaskManager $taskManager,
        private readonly IriConverterInterface $iriConverter,
        private readonly EntityManagerInterface $entityManager,
        private readonly NormalizerInterface $normalizer,
        private readonly StateMachineFactoryInterface $stateMachineFactory,
        private readonly TranslatorInterface $translator,
    )
    {
        parent::__construct($taskManager);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();

        $tasks = array_map(fn(string $iri) => $this->iriConverter->getResourceFromIri($iri), $payload['tasks'] ?? []);
        $tasks = array_filter($tasks, fn(Task $task) => !$task->isCancelled());

        $cancelled = [];
        $failed = [];

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
            $this->taskManager->cancelTasks($group, recalculatePrice: true);

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
