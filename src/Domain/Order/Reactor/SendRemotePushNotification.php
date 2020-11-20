<?php

namespace AppBundle\Domain\Order\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Message\PushNotification;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Translation\TranslatorInterface;

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

    public function __invoke($event)
    {
        $order = $event->getOrder();

        if ($event instanceof OrderCreated && $order->isFoodtech()) {

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
}
