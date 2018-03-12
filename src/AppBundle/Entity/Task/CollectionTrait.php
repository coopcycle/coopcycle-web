<?php

namespace AppBundle\Entity\Task;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

trait CollectionTrait
{
    /**
     * @ORM\Column(type="integer")
     * @Groups({"task_collection"})
     */
    protected $distance;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"task_collection"})
     */
    protected $duration;

    /**
     * @ORM\Column(type="text")
     * @Groups({"task_collection"})
     */
    protected $polyline = '';

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     * @Groups({"task_collection"})
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
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
