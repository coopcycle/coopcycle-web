<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Message\ResetRestaurantState;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class RestaurantStateSubscriber
{
    private $messages = [];

    public function __construct(private MessageBusInterface $messageBus)
    {}

    public function onFlush(OnFlushEventArgs $args)
    {
        $this->messages = [];

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $isRestaurant = function ($entity) {
            return $entity instanceof LocalBusiness;
        };

        $restaurants = array_filter($uow->getScheduledEntityUpdates(), $isRestaurant);

        if (count($restaurants) === 0) {
            return;
        }

        foreach ($restaurants as $restaurant) {

            $changeSet = $uow->getEntityChangeSet($restaurant);

            if (array_key_exists('state', $changeSet)) {

                [ $oldValue, $newValue ] = $changeSet['state'];

                if ($oldValue === 'normal' && $newValue === 'rush') {
                    $this->messages[] = new ResetRestaurantState($restaurant);
                }
            }
        }


    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $until = new \DateTimeImmutable('today 23:59');

        foreach ($this->messages as $message) {
            $this->messageBus->dispatch($message, [
                DelayStamp::delayUntil($until),
            ]);
        }
    }
}

