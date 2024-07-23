<?php

namespace AppBundle\Action\TaskList;

use AppBundle\Entity\TaskList;
use AppBundle\Serializer\TaskListNormalizer;
use AppBundle\Service\RouteOptimizer;
use Symfony\Component\HttpFoundation\JsonResponse;

final class Optimize
{

    public function __construct(
        private RouteOptimizer $optimizer,
        private TaskListNormalizer $taskListNormalizer)
    {}

    public function __invoke($data)
    {
        $optim = $this->optimizer->optimize($data);

        $data->clear();

        foreach ($optim["solution"] as $item) {
            $data->addItem($item);
        }

        return new JsonResponse([
            "solution" => $this->taskListNormalizer->normalize($data, 'jsonld', [
                'resource_class' => TaskList::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task_list']
            ]),
            "unassignedCount" => $optim["unassignedCount"]
        ]);
    }
}
