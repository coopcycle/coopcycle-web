<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait UpdateDeliveryTrait
{
    protected function updateDelivery(WoopitQuoteRequest $data, $deliveryId): Delivery
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

        $this->updateTask($data->picking, $delivery->getPickup());
        $this->updateTask($data->delivery, $delivery->getDropoff());

        return $delivery;
    }

    protected function updateTask(array $data, Task $task): Task
    {
        if (isset($data['location'])) {
            $location = $data['location'];

            $streetAddress = sprintf('%s, %s',
                implode(', ', array_filter([$location['addressLine1'], $location['addressLine2']])),
                sprintf('%s %s', $location['postalCode'], $location['city'])
            );

            $address = $this->geocoder->geocode($streetAddress);

            $task->setAddress($address);
        }

        if (isset($data['interval'])) {
            if (isset($data['interval'][0]['start'])) {
                $task->setAfter(
                    Carbon::parse($data['interval'][0]['start'])->toDateTime()
                );
            }
            if (isset($data['interval'][0]['end'])) {
                $task->setBefore(
                    Carbon::parse($data['interval'][0]['end'])->toDateTime()
                );
            }
        }

        return $task;
    }
}
