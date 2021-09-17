<?php

namespace AppBundle\Action\Urbantz;

use AppBundle\Api\Resource\UrbantzWebhook;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;

class ReceiveWebhook
{
    public function __construct(
        DeliveryRepository $deliveryRepository,
        TaskManager $taskManager,
        EntityManagerInterface $entityManager)
    {
        $this->deliveryRepository = $deliveryRepository;
        $this->taskManager = $taskManager;
        $this->entityManager = $entityManager;
    }

    public function __invoke(UrbantzWebhook $data)
    {
        $event = $data->id;

        foreach ($data->tasks as $task) {
            switch ($event) {
                case 'TaskChanged':
                    $this->onTaskChanged($task);
            }
        }

        return $data;
    }

    private function onTaskChanged(array $task)
    {
        $extTrackId = $task['extTrackId'];

        $delivery = $this->deliveryRepository->findOneByHashId($extTrackId);

        if (!$delivery) {
            return;
        }

        if ('DISCARDED' === $task['progress']) {

            foreach ($delivery->getTasks() as $task) {
                $this->taskManager->cancel($task);
            }

            // As we have configured write = false at operation level,
            // we have to flush changes here
            $this->entityManager->flush();
        }
    }
}
