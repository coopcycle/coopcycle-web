<?php

namespace AppBundle\Entity\Task;

use Doctrine\ORM\Mapping as ORM;

trait CollectionTrait
{
    /**
     * @ORM\Column(type="integer")
     */
    protected $distance;

    /**
     * @ORM\Column(type="integer")
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
