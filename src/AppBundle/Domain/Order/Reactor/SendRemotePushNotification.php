<?php

namespace AppBundle\Domain\Order\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Entity\Restaurant;
use AppBundle\Message\PushNotification;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class SendRemotePushNotification
{
    private $messageBus;
    private $iriConverter;
    private $serializer;
    private $translator;

    public function __construct(
        MessageBusInterface $messageBus,
        IriConverterInterface $iriConverter,
        SerializerInterface $serializer,
        TranslatorInterface $translator)
    {
        $this->messageBus = $messageBus;
        $this->iriConverter = $iriConverter;
        $this->serializer = $serializer;
        $this->translator = $translator;
    }

    public function __invoke($event)
    {
        $order = $event->getOrder();

        if ($event instanceof OrderCreated && $order->isFoodtech()) {

            $owners = $order->getRestaurant()->getOwners()->toArray();

            if (count($owners) > 0) {

                $restaurantNormalized = $this->normalizeRestaurant($order->getRestaurant());

                $data = [
                    'event' => json_encode([
                        'name' => 'order:created',
                        'data' => [
                            'restaurant' => $restaurantNormalized,
                            'date' => $order->getShippedAt()->format('Y-m-d'),
                            'order' => $this->iriConverter->getIriFromItem($order),
                        ]
                    ]),
                ];

                $message = $this->translator->trans('notifications.restaurant.new_order');

                $users = array_unique($owners);
                $users = array_map(function ($user) {
                    return $user->getUsername();
                }, $owners);

                $this->messageBus->dispatch(
                    new PushNotification($message, $users, $data)
                );
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
