<?php

namespace AppBundle\Action\TaskList;

use AppBundle\Entity\TaskList;
use Doctrine\ORM\EntityManagerInterface;

final class Create
{
    private $objectManager;

    public function __construct(EntityManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function __invoke($data)
    {
        $taskList = $this->objectManager->getRepository(TaskList::class)->findOneBy([
            'courier' => $data->getCourier(),
            'date' => $data->getDate(),
        ]);

        if (null !== $taskList) {

            return $taskList;
        }

        return $data;
    }
}
