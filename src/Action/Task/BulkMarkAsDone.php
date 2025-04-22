<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;


class BulkMarkAsDone extends Base
{
    use DoneTrait;

    private $iriConverter;
    private $entityManager;
    private $normalizerInterface;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        TaskManager $taskManager,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager,
        NormalizerInterface $normalizerInterface
    )
    {
        parent::__construct($tokenStorage, $taskManager);

        $this->iriConverter = $iriConverter;
        $this->entityManager = $entityManager;
        $this->normalizerInterface = $normalizerInterface;
    }

    public function __invoke(Request $request)
    {
        $payload = [];

        $content = $request->getContent();
        if (!empty($content)) {
            $payload = json_decode($content, true);
        }

        $tasks = $payload["tasks"];
        $tasksObjs = array_map(function ($taskIri) { return $this->iriConverter->getItemFromIri($taskIri); }, $tasks);

        $tasksResults= [];
        $tasksFailed= [];

        // sort tasks
        // if tasks are in a delivery make sure that the delivery order is respected so we avoid validation errors
        usort($tasksObjs, function(Task $a, Task $b) {
            if ($a->getDelivery() && $b->getDelivery()) {
                $aPos = $a->getDelivery()->findTaskPosition($a);
                $bPos = $b->getDelivery()->findTaskPosition($b);
                return $aPos < $bPos ? -1 : 1;
            } else {
                return $a->getId() < $b->getId() ? -1 : 1;
            }
        });

        foreach($tasksObjs as $task) {
            try {
                $tasksResults[] = $this->done($task, $request);
            } catch(BadRequestHttpException $e) {
                $tasksFailed[$this->iriConverter->getIriFromItem($task)] = $e->getMessage();
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => $this->normalizerInterface->normalize($tasksResults, 'jsonld', ['groups' => ['task', 'delivery', 'address']]),
            'failed' => $tasksFailed,
        ], 200);
    }
}
