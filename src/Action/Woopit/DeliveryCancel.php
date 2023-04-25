<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Delivery;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeliveryCancel
{

    public function __construct(
        Hashids $hashids12,
        TaskManager $taskManager,
        EntityManagerInterface $entityManager)
    {
        $this->hashids12 = $hashids12;
        $this->entityManager = $entityManager;
        $this->taskManager = $taskManager;
    }

    public function __invoke($deliveryId)
    {
        $decoded = $this->hashids12->decode($deliveryId);

        if (count($decoded) !== 1) {
            throw new NotFoundHttpException('Delivery id not found');
        }

        $id = current($decoded);

        $delivery = $this->entityManager->getRepository(Delivery::class)->find($id);

        if (!$delivery) {
            throw new NotFoundHttpException('Delivery not found');
        }

        foreach ($delivery->getTasks() as $task) {
            if ($task->isAssigned()) {
                throw new AccessDeniedHttpException('Tasks have already been assigned');
            }
        }

        foreach ($delivery->getTasks() as $task) {
            $this->taskManager->cancel($task);
        }

        $this->entityManager->flush();

        return;
    }
}
