<?php

namespace AppBundle\Action\Urbantz;

use AppBundle\Api\Resource\UrbantzWebhook;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\TaskManager;
use Carbon\Carbon;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class ReceiveWebhook
{
    public function __construct(
        DeliveryRepository $deliveryRepository,
        DeliveryManager $deliveryManager,
        TaskManager $taskManager,
        PhoneNumberUtil $phoneNumberUtil,
        string $country)
    {
        $this->deliveryRepository = $deliveryRepository;
        $this->deliveryManager = $deliveryManager;
        $this->taskManager = $taskManager;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->country = $country;
    }

    public function __invoke(UrbantzWebhook $data)
    {
        $event = $data->id;

        foreach ($data->tasks as $task) {
            switch ($event) {
                case UrbantzWebhook::TASKS_ANNOUNCED:
                    $data->deliveries[] = $this->onTaskAnnounced($task);
                    break;
                case UrbantzWebhook::TASK_CHANGED:
                    if ($delivery = $this->onTaskChanged($task)) {
                        $data->deliveries[] = $delivery;
                    }
                    break;
            }
        }

        return $data;
    }

    private function onTaskAnnounced(array $task): Delivery
    {
        $delivery = new Delivery();

        $address = new Address();

        $streetAddress = sprintf('%s, %s',
            ($task['source']['number'] . ' ' . $task['source']['street']),
            ($task['source']['zip'] . ' ' . $task['source']['city'])
        );

        $address->setStreetAddress($streetAddress);

        [ $longitude, $latitude ] = $task['location']['location']['geometry'] ?? [];

        if ($latitude && $longitude) {
            $address->setGeo(new GeoCoordinates($latitude, $longitude));
        } else {
            // $geoAddr = $this->geocoder->geocode($streetAddress);
            // $address->setGeo($geoAddr->getGeo());
        }

        $contactName = $task['contact']['person'] ?? $task['contact']['name'];
        $address->setContactName($contactName);

        $description = $task['instructions'] ?? '';
        if (!empty($description)) {
            $address->setDescription($description);
        }

        try {
            $phone = $task['contact']['phone'] ?? null;
            if ($phone) {
                $address->setTelephone(
                    $this->phoneNumberUtil->parse($phone, strtoupper($this->country))
                );
            }
        } catch (NumberParseException $e) {}

        $delivery->getDropoff()->setAddress($address);

        $tz = date_default_timezone_get();

        $delivery->getDropoff()->setAfter(
            Carbon::parse($task['timeWindow']['start'])->tz($tz)->toDateTime()
        );
        $delivery->getDropoff()->setBefore(
            Carbon::parse($task['timeWindow']['stop'])->tz($tz)->toDateTime()
        );

        $delivery->getDropoff()->setRef($task['id']);

        $this->deliveryManager->setDefaults($delivery);

        return $delivery;
    }

    private function onTaskChanged(array $task): ?Delivery
    {
        $extTrackId = $task['extTrackId'];

        $delivery = $this->deliveryRepository->findOneByHashId($extTrackId);

        if (!$delivery) {

            return null;
        }

        if ('DISCARDED' === $task['progress']) {

            foreach ($delivery->getTasks() as $task) {
                $this->taskManager->cancel($task);
            }

            return $delivery;
        }

        return null;
    }
}
