<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\EmailSent;
use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderDelayed;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Message\OrderReceiptEmail;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\Collection;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

class SendEmail
{
    private $emailManager;
    private $settingsManager;
    private $eventBus;

    public function __construct(
        EmailManager $emailManager,
        SettingsManager $settingsManager,
        MessageBus $eventBus,
        MessageBusInterface $messageBus)
    {
        $this->emailManager = $emailManager;
        $this->settingsManager = $settingsManager;
        $this->eventBus = $eventBus;
        $this->messageBus = $messageBus;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();
        $customer = $order->getCustomer();

        // This may happen when the order has been
        // created by manually defining a price
        if (null === $customer) {
            return;
        }

        if ($event instanceof OrderAccepted) {

            $message = $this->emailManager->createOrderAcceptedMessage($order);
            $this->emailManager->sendTo($message, $order->getCustomer()->getEmail());
            $this->eventBus->handle(new EmailSent($order, $order->getCustomer()->getEmail()));

            // When this is a multi-vendor order,
            // we notify owners when the order has been *ACCEPTED*
            if ($order->isMultiVendor()) {
                $this->notifyOwners($order);
            }
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
            if ($order->hasVendor()) {
                $this->handleOrderCreatedWithVendor($event);
            } else {
                $this->handleOrderCreated($event);
            }
        }

        if ($event instanceof OrderFulfilled && $order->hasVendor()) {
            // This email is sent asynchronously
            $this->messageBus->dispatch(
                new OrderReceiptEmail($order->getNumber())
            );
        }
    }

    private function handleOrderCreatedWithVendor(Event $event)
    {
        $order = $event->getOrder();

        // Send email to customer
        $this->emailManager->sendTo(
            $this->emailManager->createOrderCreatedMessageForCustomer($order),
            sprintf('%s <%s>', $order->getCustomer()->getFullName(), $order->getCustomer()->getEmail())
        );
        $this->eventBus->handle(new EmailSent($order, $order->getCustomer()->getEmail()));

        // Send email to admin
        $this->emailManager->sendTo(
            $this->emailManager->createOrderCreatedMessageForAdmin($order),
            $this->settingsManager->get('administrator_email')
        );
        $this->eventBus->handle(new EmailSent($order, $this->settingsManager->get('administrator_email')));

        // Send email to shop owners
        // When this is a multi vendor order,
        // we will send the email when the order is *ACCEPTED*
        if ($order->isMultiVendor()) {
            return;
        }

        $this->notifyOwners($order);
    }

    private function notifyOwners(OrderInterface $order)
    {
        foreach ($order->getRestaurants() as $restaurant) {
            $this->sendEmailToOwners($order, $restaurant);
        }
    }

    private function sendEmailToOwners(OrderInterface $order, LocalBusiness $restaurant)
    {
        $owners = $restaurant->getOwners()->toArray();

        if (count($owners) === 0) {
            return;
        }

        $ownerMails = [];
        foreach ($owners as $owner) {
            $ownerMails[] = sprintf('%s <%s>', $owner->getFullName(), $owner->getEmail());
        }

        $this->emailManager->sendTo(
            $this->emailManager->createOrderCreatedMessageForOwner($order, $restaurant),
            ...$ownerMails
        );
        foreach ($ownerMails as $ownerMail) {
            $address = Address::fromString($ownerMail);
            $this->eventBus->handle(new EmailSent($order, $address->getAddress()));
        }
    }

    private function handleOrderCreated(Event $event)
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
