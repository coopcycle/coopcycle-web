<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Event\DeliveryCreateEvent;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use Predis\Client as Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class DeliverySubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $redis;
    private $logger;

    public function __construct(DoctrineRegistry $doctrine,
        Redis $redis,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            DeliveryCreateEvent::NAME => 'onDeliveryCreated',
        ];
    }

    public function onDeliveryCreated(DeliveryCreateEvent $event)
    {
        $delivery = $event->getDelivery();

        $this->logger->info(sprintf('Delivery #%d created', $delivery->getId()));
    }
}
