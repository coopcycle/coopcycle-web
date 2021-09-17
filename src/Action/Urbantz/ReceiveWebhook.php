<?php

namespace AppBundle\Action\Urbantz;

use AppBundle\Api\Resource\UrbantzWebhook;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\DeliveryRepository;

class ReceiveWebhook
{
    public function __construct(DeliveryRepository $deliveryRepository)
    {
        $this->deliveryRepository = $deliveryRepository;
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

        // TODO Update delivery
    }
}
