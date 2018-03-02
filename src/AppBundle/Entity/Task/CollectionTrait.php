<?php

namespace AppBundle\Entity\Task;

use Doctrine\ORM\Mapping as ORM;
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
}
