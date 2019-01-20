<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\EmailSent;
use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderDelayed;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use SimpleBus\Message\Bus\MessageBus;

class SendEmail
{
    private $emailManager;
    private $settingsManager;
    private $eventBus;

    public function __construct(
        EmailManager $emailManager,
        SettingsManager $settingsManager,
        MessageBus $eventBus)
    {
        $this->emailManager = $emailManager;
        $this->settingsManager = $settingsManager;
        $this->eventBus = $eventBus;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();

        if ($event instanceof OrderAccepted) {
            $message = $this->emailManager->createOrderAcceptedMessage($order);
            $this->emailManager->sendTo($message, $order->getCustomer()->getEmail());
            $this->eventBus->handle(new EmailSent($order, $order->getCustomer()->getEmail()));
        }

        if ($event instanceof OrderRefused || $event instanceof OrderCancelled) {
            $message = $this->emailManager->createOrderCancelledMessage($order);
            $this->emailManager->sendTo($message, $order->getCustomer()->getEmail());
            $this->eventBus->handle(new EmailSent($order, $order->getCustomer()->getEmail()));
        }

        if ($event instanceof OrderDelayed) {
            $message = $this->emailManager->createOrderDelayedMessage($order, $event->getDelay());
            $this->emailManager->sendTo($message, $order->getCustomer()->getEmail());
            $this->eventBus->handle(new EmailSent($order, $order->getCustomer()->getEmail()));
        }

        if ($event instanceof OrderCreated) {
            if ($order->isFoodtech()) {
                $this->handleFoodtechOrderCreated($event);
            } else {
                $this->handleOnDemandOrderCreated($event);
            }
        }
    }

    private function handleFoodtechOrderCreated(Event $event)
    {
        $order = $event->getOrder();

        // Send email to customer
        $this->emailManager->sendTo(
            $this->emailManager->createOrderCreatedMessageForCustomer($order),
            [$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]
        );
        $this->eventBus->handle(new EmailSent($order, $order->getCustomer()->getEmail()));

        // Send email to admin
        $this->emailManager->sendTo(
            $this->emailManager->createOrderCreatedMessageForAdmin($order),
            $this->settingsManager->get('administrator_email')
        );
        $this->eventBus->handle(new EmailSent($order, $this->settingsManager->get('administrator_email')));

        // Send email to restaurant owners
        $owners = $order->getRestaurant()->getOwners()->toArray();
        if (count($owners) > 0) {

            $ownerMails = [];
            foreach ($owners as $owner) {
                $ownerMails[$owner->getEmail()] = $owner->getFullName();
            }

            $this->emailManager->sendTo(
                $this->emailManager->createOrderCreatedMessageForOwner($order),
                $ownerMails
            );
            foreach ($ownerMails as $email => $alias) {
                $this->eventBus->handle(new EmailSent($order, $email));
            }
        }
    }

    private function handleOnDemandOrderCreated(Event $event)
    {
        $order = $event->getOrder();

        // Send email to customer
        $this->emailManager->sendTo(
            $this->emailManager->createOrderCreatedMessageForCustomer($order),
            $order->getCustomer()->getEmail()
        );
        $this->eventBus->handle(new EmailSent($order, $order->getCustomer()->getEmail()));

        // Send email to admin
        $this->emailManager->sendTo(
            $this->emailManager->createOrderCreatedMessageForAdmin($order),
            $this->settingsManager->get('administrator_email')
        );
        $this->eventBus->handle(new EmailSent($order, $this->settingsManager->get('administrator_email')));
    }
}
