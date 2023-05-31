<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Sylius\OrderInvitation;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Ramsey\Uuid\Uuid;

class OrderInvitationListener
{
    public function prePersist(OrderInvitation $invitation, LifecycleEventArgs $args)
    {
        $invitation->setSlug(Uuid::uuid4()->toString());
    }
}
