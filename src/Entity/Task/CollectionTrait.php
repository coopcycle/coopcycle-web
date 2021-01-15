<?php

namespace AppBundle\Entity\Task;

use Symfony\Component\Serializer\Annotation\Groups;

trait CollectionTrait
{
    /**
     * @Groups({"task_collection", "task_collections"})
     */
    protected $distance;

    /**
     * @Groups({"task_collection", "task_collections"})
     */
    protected $duration;

    /**
     * @Groups({"task_collection", "task_collections"})
     */
    protected $polyline = '';

    /**
     * @Groups({"task_collection"})
     */
    private $createdAt;

    /**
     * @Groups({"task_collection"})
     */
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
}
