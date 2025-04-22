<?php

namespace AppBundle\MessageHandler\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderStateChanged;
use AppBundle\Message\PushNotification;
use AppBundle\Security\UserManager;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler()]
class SendRemotePushNotification
{
    public function __construct(
        private UserManager $userManager,
        private MessageBusInterface $messageBus,
        private IriConverterInterface $iriConverter,
        private TranslatorInterface $translator,
        private LoggerInterface $pushNotificationLogger)
    {
    }

    private function shouldSendNotification(OrderCreated|OrderAccepted|OrderStateChanged $event): bool
    {
        $order = $event->getOrder();

        if (!$order->hasVendor()) {
            return false;
        }

        if ($order->isMultiVendor()) {
            return $event instanceof Event\OrderAccepted;
        } else {
            $vendor = $order->getVendors()->first();

            if ($restaurant = $vendor->getRestaurant()) {
                if ($restaurant->isAutoAcceptOrdersEnabled()) {
                    return $event instanceof Event\OrderStateChanged && $event->getOrder()->getState() === OrderInterface::STATE_ACCEPTED;
                }
            }
            return $event instanceof Event\OrderCreated;
        }
    }

    public function __invoke(Event $event)
    {
        if (!$this->shouldSendNotification($event)) {
            return;
        }

        $order = $event->getOrder();

        $message = '';

        if ($event instanceof Event\OrderCreated) {
            $message = $this->translator->trans('notifications.restaurant.new_order');
        } else if ($event instanceof Event\OrderAccepted || ($event instanceof Event\OrderStateChanged && $order->getState() === OrderInterface::STATE_ACCEPTED)) {
            $message = $this->translator->trans('notifications.restaurant.accepted_order');
        }

        if (empty($message)) {
            $this->pushNotificationLogger->warning('No message to send', [
                'event' => $event::messageName(),
                'order' => $order->getId()
            ]);
            return;
        }

        // Send to admins
        $admins = array_map(function ($user) {
            return $user->getUsername();
        }, $this->userManager->findUsersByRole('ROLE_ADMIN'));

        $this->messageBus->dispatch(
            new PushNotification($message, $admins)
        );

        // Send to owners
        $owners = $order->getNotificationRecipients()->toArray();

        if (count($owners) > 0) {

            $data = [
                'event' => [
                    'name' => 'order:created',
                    'data' => [
                        'order' => $this->iriConverter->getIriFromItem($order),
                    ]
                ],
            ];

            $users = array_map(function ($user) {
                return $user->getUsername();
            }, $owners);
            $users = array_unique($owners);

            $this->messageBus->dispatch(
                new PushNotification($message, $users, $data)
            );
        }
    }
}
