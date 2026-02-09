<?php

namespace AppBundle\Doctrine\EventSubscriber;

use Carbon\Carbon;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Gedmo\Timestampable\Traits\Timestampable;

class MockDateSubscriber
{
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $this->setEntityDates($entity);
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        $this->setEntityDates($entity);
    }

    private function setEntityDates($entity)
    {
        if (in_array(Timestampable::class, class_uses_recursive($entity))) {
            $entity->setCreatedAt(clone Carbon::now());
            $entity->setUpdatedAt(clone Carbon::now());
        }
    }
}
