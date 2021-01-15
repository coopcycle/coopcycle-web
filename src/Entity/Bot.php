<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Utils\GeoUtils;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Validator\Constraints as Assert;

class Bot
{
    use Timestampable;

    private $id;
    private $user;
    private $lastPosition;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     *
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return self
     */
    public function setLastPosition(GeoCoordinates $coords)
    {
        $value = GeoUtils::asPoint($coords);

        $this->lastPosition = $value;

        return $this;
    }

    /**
     * @return GeoCoordinates|null
     */
    public function getLastPosition()
    {
        if (null !== $this->lastPosition) {
            return GeoUtils::asGeoCoordinates($this->lastPosition);
        }

        return null;
    }
}
