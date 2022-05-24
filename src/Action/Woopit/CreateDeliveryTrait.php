<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

trait CreateDeliveryTrait
{
    protected function createDelivery(WoopitQuoteRequest $data): Delivery
    {
        $pickup = $this->createTask($data->picking, Task::TYPE_PICKUP);
        $dropoff = $this->createTask($data->delivery, Task::TYPE_DROPOFF);

        $delivery = Delivery::createWithTasks($pickup, $dropoff);

        $this->deliveryManager->setDefaults($delivery);

        return $delivery;
    }

    protected function createTask(array $data, string $type): Task
    {
        $location = $data['location'];

        $streetAddress = sprintf('%s, %s',
            implode(', ', array_filter([$location['addressLine1'], $location['addressLine2']])),
            sprintf('%s %s', $location['postalCode'], $location['city'])
        );

        $address = $this->geocoder->geocode($streetAddress);

        $task = new Task();
        $task->setType($type);
        $task->setAddress($address);

        $task->setAfter(
            Carbon::parse($data['interval'][0]['start'])->toDateTime()
        );
        $task->setBefore(
            Carbon::parse($data['interval'][0]['end'])->toDateTime()
        );

        return $task;
    }
}
