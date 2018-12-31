<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Service\NotificationManager;

class SendWebNotification
{
    private $notificationManager;

    public function __construct(NotificationManager $notificationManager)
    {
        $this->notificationManager = $notificationManager;
    }

    public function __invoke(OrderCreated $event)
    {
        $order = $event->getOrder();

        $notifications = $this->notificationManager
            ->createForAdministrators('notifications.order.created');

        foreach ($notifications as $notification) {

            $notification->setRouteName('admin_order');
            $notification->setRouteParameters(['id' => $order->getId()]);

            $this->notificationManager->push($notification);
        }

        if ($order->isFoodtech()) {

            $owners = $order->getRestaurant()->getOwners()->toArray();

            if (count($owners) === 0) {
                return;
            }

            // Add web notification
            foreach ($owners as $owner) {

                $notification = $this->notificationManager
                    ->createForUser($owner, 'notifications.order.created');

                $notification->setRouteName('profile_restaurant_dashboard');
                $notification->setRouteParameters([
                    'restaurantId' => $order->getRestaurant()->getId(),
                    'order' => $order->getId()
                ]);

                $this->notificationManager->push($notification);
            }
        }
    }
}
