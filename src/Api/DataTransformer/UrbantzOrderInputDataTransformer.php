<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Api\Dto\UrbantzOrderInput;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Security\TokenStoreExtractor;
use Carbon\Carbon;

class UrbantzOrderInputDataTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        // This transformer is executed *BEFORE* DeliverySubscriber,
        // which will add the defaults

        $task = $data->tasks[0];

        $delivery = new Delivery();

        $address = new Address();

        $streetAddress = sprintf('%s, %s',
            ($task['address']['number'] . ' ' . $task['address']['street']),
            ($task['address']['zip'] . ' ' . $task['address']['city'])
        );

        $address->setStreetAddress($streetAddress);
        $address->setGeo(new GeoCoordinates($task['address']['latitude'], $task['address']['longitude']));

        $contactName = $task['contact']['person'] ?? $task['contact']['name'];
        $address->setContactName($contactName);

        $description = $task['instructions'] ?? '';
        if (!empty($description)) {
            $address->setDescription($description);
        }

        $delivery->getDropoff()->setAddress($address);

        $tz = date_default_timezone_get();

        $delivery->getDropoff()->setAfter(
            Carbon::parse($task['timeWindow']['start'])->tz($tz)->toDateTime()
        );
        $delivery->getDropoff()->setBefore(
            Carbon::parse($task['timeWindow']['stop'])->tz($tz)->toDateTime()
        );

        $delivery->getDropoff()->setRef($task['taskId']);

        return $delivery;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Delivery) {
          return false;
        }

        return Delivery::class === $to && UrbantzOrderInput::class === ($context['input']['class'] ?? null);
    }
}
