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
    private $restaurantRepository;

    public function __construct(
        EmailManager $emailManager,
        SettingsManager $settingsManager,
        MessageBus $eventBus,
        MessageBusInterface $messageBus,
        LocalBusinessRepository $restaurantRepository)
    {
        $this->emailManager = $emailManager;
        $this->settingsManager = $settingsManager;
        $this->eventBus = $eventBus;
        $this->messageBus = $messageBus;
        $this->restaurantRepository = $restaurantRepository;
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

        if ($event instanceof OrderFulfilled && $order->hasVendor()) {
            // This email is sent asynchronously
            $this->messageBus->dispatch(
                new OrderReceiptEmail($order->getNumber())
            );
        }
    }

    private function handleFoodtechOrderCreated(Event $event)
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

        // Send email to restaurant owners
        $vendor = $order->getVendor();

        if ($vendor->isHub()) {

            $restaurants = [];
            foreach ($order->getItems() as $orderItem) {
                $product = $orderItem->getVariant()->getProduct();
                $restaurant = $this->restaurantRepository->findOneByProduct($product);
                if (!in_array($restaurant, $restaurants, true)) {
                    $restaurants[] = $restaurant;
                }
            }

            foreach ($restaurants as $restaurant) {
                $this->sendEmailToOwners($order, $restaurant);
            }

        } else {
            $this->sendEmailToOwners($order, $vendor->getRestaurant());
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
