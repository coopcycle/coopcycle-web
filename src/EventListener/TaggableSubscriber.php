<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Service\TagManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class TaggableSubscriber implements EventSubscriber
{
    private $tagManager;

    public function __construct(TagManager $tagManager)
    {
        $this->tagManager = $tagManager;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::postPersist,
            Events::postLoad,
        );
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof TaggableInterface) {
            $this->tagManager->addTagsAndFlush($entity, $entity->getTags());
        }
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof TaggableInterface) {
            $entity->setTagManager($this->tagManager);
        }
    }
}
