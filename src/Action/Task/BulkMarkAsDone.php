<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Api\IriConverterInterface;
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
        NormalizerInterface $normalizerInterface)
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

        $tasksResults= [];
        $tasksFailed= [];

        // sort tasks by iri
        // if a pickup and its dropoff have to be mark as done we need to order them so the pickup is marked first
        usort($tasks, function($a, $b) {
            return $a < $b ? -1 : 1;
        });

        foreach($tasks as $task) {
            $taskObj = $this->iriConverter->getItemFromIri($task);
            try {
                $tasksResults[] = $this->done($taskObj, $request);
            } catch(BadRequestHttpException $e) {
                $tasksFailed[$task] = $e->getMessage();
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => $this->normalizerInterface->normalize($tasksResults, 'jsonld', ['groups' => ['task', 'delivery', 'address']]),
            'failed' => $tasksFailed,
        ], 200);
    }
}
