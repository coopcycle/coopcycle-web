<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryEvent;
use AppBundle\Entity\StripePayment;
use AppBundle\Event\DeliveryConfirmEvent;
use AppBundle\Event\DeliveryCreateEvent;
use AppBundle\Service\NotificationManager;
use Doctrine\Common\Util\ClassUtils;
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
            DeliveryConfirmEvent::NAME => 'onDeliveryConfirmed',
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

        if ($delivery->getStatus() === Delivery::STATUS_TO_BE_CONFIRMED) {
            $administrators = $this->doctrine
                ->getRepository(ApiUser::class)
                ->createQueryBuilder('u')
                ->where('u.roles LIKE :roles')
                ->setParameter('roles', '%ROLE_ADMIN%')
                ->getQuery()
                ->getResult();
            foreach ($administrators as $administrator) {
                $this->notificationManager->notifyDeliveryHasToBeConfirmed($delivery, $administrator->getEmail());
            }
        }

        $this->persistEvent($delivery, 'CREATE');
    }

    public function onDeliveryConfirmed(DeliveryConfirmEvent $event)
    {
        $delivery = $event->getDelivery();

        $this->logger->info(sprintf('Delivery #%d confirmed', $delivery->getId()));

        $stripePayment = $this->doctrine->getRepository(StripePayment::class)
            ->findOneBy([
                'resourceClass' => ClassUtils::getClass($delivery),
                'resourceId' => $delivery->getId(),
            ]);

        $this->notificationManager->notifyDeliveryConfirmed($delivery, $stripePayment);
        $this->persistEvent($delivery, 'CONFIRM');
    }
}
