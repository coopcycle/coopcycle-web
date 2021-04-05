<?php

namespace AppBundle\Domain\Order\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Message\PushNotification;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendRemotePushNotification
{
    private $userManager;
    private $messageBus;
    private $iriConverter;
    private $translator;

    public function __construct(
        UserManagerInterface $userManager,
        MessageBusInterface $messageBus,
        IriConverterInterface $iriConverter,
        TranslatorInterface $translator)
    {
        $this->userManager = $userManager;
        $this->messageBus = $messageBus;
        $this->iriConverter = $iriConverter;
        $this->translator = $translator;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();

        if (!$order->hasVendor()) {
            return;
        }

        $shouldSendNotification = $order->isMultiVendor() ?
            $event instanceof Event\OrderAccepted : $event instanceof Event\OrderCreated;

        if (!$shouldSendNotification) {
            return;
        }

        // TODO Send a different message when event is "order:accepted"
        $message = $this->translator->trans('notifications.restaurant.new_order');

        // Send to admins
        $admins = array_map(function ($user) {
            return $user->getUsername();
        }, $this->userManager->findUsersByRole('ROLE_ADMIN'));

        $this->messageBus->dispatch(
            new PushNotification($message, $admins)
        );

        // Send to owners
        $owners = $order->getVendor()->getOwners()->toArray();

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
