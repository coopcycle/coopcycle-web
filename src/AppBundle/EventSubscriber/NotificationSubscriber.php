<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Restaurant;
use AppBundle\Event;
use AppBundle\Service\EmailManager;
use AppBundle\Service\NotificationManager;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\SettingsManager;
use Predis\Client as Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class NotificationSubscriber implements EventSubscriberInterface
{
    private $redis;
    private $notificationManager;
    private $remotePushNotificationManager;
    private $emailManager;
    private $settingsManager;
    private $serializer;
    private $logger;

    public function __construct(
        Redis $redis,
        NotificationManager $notificationManager,
        RemotePushNotificationManager $remotePushNotificationManager,
        EmailManager $emailManager,
        SettingsManager $settingsManager,
        SerializerInterface $serializer,
        LoggerInterface $logger)
    {
        $this->redis = $redis;
        $this->notificationManager = $notificationManager;
        $this->remotePushNotificationManager = $remotePushNotificationManager;
        $this->emailManager = $emailManager;
        $this->settingsManager = $settingsManager;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            Event\OrderCreateEvent::NAME      => 'onOrderCreated',
            Event\OrderAcceptEvent::NAME      => 'onOrderAccepted',
            Event\OrderCancelEvent::NAME      => 'onOrderCancelled',
            Event\PaymentAuthorizeEvent::NAME => 'onPaymentAuthorized',
        ];
    }

    public function onOrderCreated(Event\OrderCreateEvent $event)
    {
        $order = $event->getOrder();

        $notifications = $this->notificationManager
            ->createForAdministrators('notifications.order.created');

        foreach ($notifications as $notification) {

            $notification->setRouteName('admin_order');
            $notification->setRouteParameters(['id' => $order->getId()]);

            $this->notificationManager->push($notification);
        }

        if (!$order->isFoodtech()) {
            $this->emailManager->sendTo(
                $this->emailManager->createOrderCreatedMessageForCustomer($order),
                $order->getCustomer()->getEmail()
            );
            $this->emailManager->sendTo(
                $this->emailManager->createOrderCreatedMessageForAdmin($order),
                $this->settingsManager->get('administrator_email')
            );
        }
    }

    public function onOrderAccepted(Event\OrderAcceptEvent $event)
    {
        $order = $event->getOrder();

        $message = $this->emailManager->createOrderAcceptedMessage($order);
        $this->emailManager->sendTo($message, $order->getCustomer()->getEmail());
    }

    public function onOrderCancelled(Event\OrderCancelEvent $event)
    {
        $order = $event->getOrder();

        $message = $this->emailManager->createOrderCancelledMessage($order);
        $this->emailManager->sendTo($message, $order->getCustomer()->getEmail());
    }

    public function onPaymentAuthorized(Event\PaymentAuthorizeEvent $event)
    {
        $payment = $event->getPayment();
        $order = $payment->getOrder();

        if ($order->isFoodtech()) {

            $this->redis->publish(
                sprintf('restaurant:%d:orders', $order->getRestaurant()->getId()),
                $this->serializer->serialize($order, 'jsonld', ['groups' => ['order']])
            );

            // Send email to customer
            $this->emailManager->sendTo(
                $this->emailManager->createOrderCreatedMessageForCustomer($order),
                [$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]
            );

            // Send email to admin
            $this->emailManager->sendTo(
                $this->emailManager->createOrderCreatedMessageForAdmin($order),
                $this->settingsManager->get('administrator_email')
            );

            /* Send notifications to owners */

            $owners = $order->getRestaurant()->getOwners()->toArray();

            if (count($owners) === 0) {
                return;
            }

            $ownerMails = [];

            // Add web notification
            foreach ($owners as $owner) {

                $notification = $this->notificationManager
                    ->createForUser($owner, 'notifications.order.created');

                $notification->setRouteName('profile_restaurant_dashboard_order');
                $notification->setRouteParameters([
                    'restaurantId' => $order->getRestaurant()->getId(),
                    'orderId' => $order->getId()
                ]);

                $this->notificationManager->push($notification);

                $ownerMails[$owner->getEmail()] = $owner->getFullName();
            }

            // Send email
            $this->emailManager->sendTo(
                $this->emailManager->createOrderCreatedMessageForOwner($order),
                $ownerMails
            );

            // Send remote push notification

            $restaurantNormalized = $this->serializer->normalize($order->getRestaurant(), 'jsonld', [
                'resource_class' => Restaurant::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get'
            ]);

            $restaurantNormalized = [
                '@id' => $restaurantNormalized['@id'],
                'name' => $restaurantNormalized['name']
            ];

            $data = [
                'event' => [
                    'name' => 'order:created',
                    'data' => [
                        'restaurant' => $restaurantNormalized,
                        'date' => $order->getShippedAt()->format('Y-m-d')
                    ]
                ]
            ];
            $this->remotePushNotificationManager
                ->send('New order to accept', $owners, $data);
        }
    }
}
