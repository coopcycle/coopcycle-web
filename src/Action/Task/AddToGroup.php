<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class AddToGroup extends Base
{
    public function __construct(
        TaskManager $taskManager,
        EntityManagerInterface $objectManager,
        SerializerInterface $serializer

    )
    {
        $this->taskManager = $taskManager;
        $this->objectManager = $objectManager;
        $this->serializer = $serializer;
    }

    public function __invoke($data, Request $request)
    {
        $taskGroup = $this->serializer->deserialize($request->getContent(), TaskGroup::class, 'jsonld');

        $this->taskManager->addToGroup($taskGroup->getTasks(), $data);

        $this->objectManager->flush();

        return $data;
    }
}
