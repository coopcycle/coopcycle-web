<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait UpdateDeliveryTrait
{
    use PackagesTrait;
    use AddressTrait;

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

        $this->parseAndApplyPackages($data, $delivery->getPickup());

        return $delivery;
    }

    protected function updateTask(array $data, Task $task): Task
    {
        if (isset($data['address'])) {
            $addressData = $data['address'];

            $streetAddress = sprintf('%s %s %s', $addressData['addressLine1'], $addressData['postalCode'], $addressData['city']);

            $address = $this->geocoder->geocode($streetAddress);

            $address->setDescription(
                $this->getAddressDescription($addressData)
            );

            $task->setAddress($address);
        }

        if (isset($data['contact'])) {
            $contact = $data['contact'];

            if (isset($contact['firstName']) && isset($contact['lastName'])) {
                $task->getAddress()->setContactName($contact['firstName'] . ' ' . $contact['lastName']);
            }

            if (isset($contact['phone'])) {
                $task->getAddress()->setTelephone($this->phoneNumberUtil->parse($contact['phone']));
            }
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
