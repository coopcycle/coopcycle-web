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
    use PackagesTrait;
    use AddressTrait;

    protected function createDelivery(WoopitQuoteRequest $data): Delivery
    {
        $pickup = $this->createTask($data->picking, Task::TYPE_PICKUP);
        $dropoff = $this->createTask($data->delivery, Task::TYPE_DROPOFF);

        $this->parseAndApplyPackages($data, $pickup);

        $delivery = Delivery::createWithTasks($pickup, $dropoff);

        $this->deliveryManager->setDefaults($delivery);

        return $delivery;
    }

    protected function createTask(array $data, string $type): Task
    {
        $location = $data['location'];

        $streetAddress = sprintf('%s %s %s', $location['addressLine1'], $location['postalCode'], $location['city']);

        $address = $this->geocoder->geocode($streetAddress);

        $address->setDescription(
            $this->getAddressDescription($location)
        );

        if (isset($data['contact'])) {
            $contact = $data['contact'];
            $address->setContactName($contact['firstName'] . ' ' . $contact['lastName']);
            $address->setTelephone($this->phoneNumberUtil->parse($contact['phone']));

            // Should be save $contact['email']? User or guest customer?
        }

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
