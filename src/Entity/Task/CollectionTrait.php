<?php

namespace AppBundle\Entity\Task;

use Symfony\Component\Serializer\Annotation\Groups;

trait CollectionTrait
{
    #[Groups(['delivery', 'task_collection', 'task_collections', 'task_list'])]
    protected $distance;

    #[Groups(['delivery', 'task_collection', 'task_collections', 'task_list'])]
    protected $duration;

    #[Groups(['delivery', 'task_collection', 'task_collections', 'task_list'])]
    protected $polyline = '';

    #[Groups(['task_collection', 'task_list'])]
    private $createdAt;

    #[Groups(['task_collection', 'task_list'])]
    private $updatedAt;

    public function getDistance()
    {
        return $this->distance;
    }

    public function setDistance($distance)
    {
        $this->distance = $distance;

        return $this;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function setDuration($duration)
    {
        $this->duration = $duration;

        return $this;
    }

    public function getPolyline()
    {
        return $this->polyline;
    }

    public function setPolyline($polyline)
    {
        $this->polyline = $polyline;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Gedmo owns this field on an ordinary update, but a collection whose
     * items changed has no changeset of its own: adding or removing an item
     * touches the item rows, not this one. Doctrine would then issue no
     * UPDATE at all, leaving both `updatedAt` and the optimistic locking
     * version stale while the collection's contents have in fact changed.
     *
     * @see \AppBundle\Doctrine\EventSubscriber\TaskCollectionVersionSubscriber
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
