<?php

namespace AppBundle\Domain\Order\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\RemotePushNotificationManager;
use Symfony\Component\Serializer\SerializerInterface;

class SendRemotePushNotification
{
    private $remotePushNotificationManager;
    private $iriConverter;
    private $serializer;

    public function __construct(
        RemotePushNotificationManager $remotePushNotificationManager,
        IriConverterInterface $iriConverter,
        SerializerInterface $serializer)
    {
        $this->remotePushNotificationManager = $remotePushNotificationManager;
        $this->iriConverter = $iriConverter;
        $this->serializer = $serializer;
    }

    public function __invoke($event)
    {
        $order = $event->getOrder();

        if ($order->isFoodtech()) {

            if ($event instanceof OrderCreated) {

                $owners = $order->getRestaurant()->getOwners()->toArray();

                if (count($owners) > 0) {

                    $restaurantNormalized = $this->normalizeRestaurant($order->getRestaurant());

                    $data = [
                        'event' => [
                            'name' => 'order:created',
                            'data' => [
                                'restaurant' => $restaurantNormalized,
                                'date' => $order->getShippedAt()->format('Y-m-d'),
                                'order' => $this->iriConverter->getIriFromItem($order),
                            ]
                        ]
                    ];

                    // TODO Translate notification title
                    $this->remotePushNotificationManager
                        ->send('New order to accept', $owners, $data);
                }
            }

            if ($event instanceof OrderAccepted) {

                $data = [
                    'event' => [
                        'name' => 'order:accepted',
                        'data' => [
                            'order' => $this->iriConverter->getIriFromItem($order),
                        ]
                    ]
                ];

                // TODO Translate notification title
                $this->remotePushNotificationManager
                    ->send('Order accepted', $order->getCustomer(), $data);
            }
        }


    }

    private function normalizeRestaurant(Restaurant $restaurant)
    {
        $restaurantNormalized = $this->serializer->normalize($restaurant, 'jsonld', [
            'resource_class' => Restaurant::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get'
        ]);

        return [
            '@id' => $restaurantNormalized['@id'],
            'name' => $restaurantNormalized['name']
        ];
    }
}
