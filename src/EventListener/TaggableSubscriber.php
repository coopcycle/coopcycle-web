<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Service\TagManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

class TaggableSubscriber implements EventSubscriber
{
    private $tagManager;
    private $logger;

    private $added;
    private $removed;

    public function __construct(TagManager $tagManager, LoggerInterface $logger)
    {
        $this->tagManager = $tagManager;
        $this->logger = $logger;

        $this->added   = new \SplObjectStorage();
        $this->removed = new \SplObjectStorage();
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postLoad,
            Events::postFlush,
        );
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $this->added   = new \SplObjectStorage();
        $this->removed = new \SplObjectStorage();

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $isTaggable = function ($entity) {
            return $entity instanceof TaggableInterface;
        };

        $inserts = array_filter($uow->getScheduledEntityInsertions(), $isTaggable);
        $updates = array_filter($uow->getScheduledEntityUpdates(), $isTaggable);

        $taggables = array_merge($inserts, $updates);

        if (count($taggables) === 0) {
            return;
        }

        $this->logger->debug(sprintf('There are %d taggables in flush phase', count($taggables)));

        foreach ($taggables as $taggable) {
            [ $added, $removed ] = $this->tagManager->update($taggable);

            if (count($added) > 0) {
                $this->added[$taggable] = $added;
            }

            if (count($removed) > 0) {
                $this->removed[$taggable] = $removed;
            }
        }
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof TaggableInterface) {

            $tags = array_map(
                fn ($tag) => $tag['slug'],
                $this->tagManager->getTags($entity)
            );

            if (count($tags) > 0) {
                $entity->setTags($tags);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->added) === 0 && count($this->removed) === 0) {
            return;
        }

        $this->logger->debug(sprintf('There are %d taggables to add, and %d taggables to remove',
            count($this->added),
            count($this->removed)
        ));

        $taggables = new \SplObjectStorage();

        $em = $args->getEntityManager();

        foreach ($this->added as $taggable) {
            $callbacks = $this->added[$taggable];
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    $tagging = call_user_func_array($callback, [ $taggable ]);
                    if (null !== $tagging) {
                        $em->persist($tagging);
                        if (!$taggables->contains($taggable)) {
                            $taggables->attach($taggable);
                        }
                    }
                }
            }
        }

        foreach ($this->removed as $taggable) {
            $callbacks = $this->removed[$taggable];
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    $tagging = call_user_func_array($callback, [ $taggable ]);
                    if (null !== $tagging) {
                        $em->remove($tagging);
                        if (!$taggables->contains($taggable)) {
                            $taggables->attach($taggable);
                        }
                    }
                }
            }
        }

        if (count($taggables) > 0) {
            $em->flush();
            foreach ($taggables as $taggable) {
                $this->tagManager->clearCache($taggable);
            }
        }

        $this->added->removeAll($this->added);
        $this->removed->removeAll($this->removed);
    }
}
