<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryEvent;
use AppBundle\Event\DeliveryCreateEvent;
use AppBundle\Service\NotificationManager;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use Predis\Client as Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class DeliverySubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $notificationManager;
    private $redis;
    private $logger;

    public function __construct(DoctrineRegistry $doctrine,
        NotificationManager $notificationManager,
        Redis $redis,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->notificationManager = $notificationManager;
        $this->redis = $redis;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            DeliveryCreateEvent::NAME => 'onDeliveryCreated',
        ];
    }

    private function persistEvent(Delivery $delivery, $eventName)
    {
        $event = new DeliveryEvent($delivery, $eventName);

        $this->doctrine->getManagerForClass(DeliveryEvent::class)->persist($event);
        $this->doctrine->getManagerForClass(DeliveryEvent::class)->flush();
    }

    public function onDeliveryCreated(DeliveryCreateEvent $event)
    {
        $delivery = $event->getDelivery();

        $this->logger->info(sprintf('Delivery #%d created', $delivery->getId()));

        $this->persistEvent($delivery, 'CREATE');
    }
}
