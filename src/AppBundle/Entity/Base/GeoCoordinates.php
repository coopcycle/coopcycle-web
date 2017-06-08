<?php

namespace AppBundle\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * The geographic coordinates of a place or event.
 *
 * @see http://schema.org/GeoCoordinates Documentation on Schema.org
 */
class GeoCoordinates
{
    /**
     * @var float The latitude of a location. For example `37.42242`.
     *
     * @Groups({"place"})
     * @Assert\Type(type="float")
     */
    private $latitude;

    /**
     * @var float The longitude of a location. For example `-122.08585`.
     *
     * @Groups({"place"})
     * @Assert\Type(type="float")
     */
    private $longitude;

    public function __construct($latitude = null, $longitude = null)
    {
        $this->latitude = (float) $latitude;
        $this->longitude = (float) $longitude;
    }

    /**
     * Sets latitude.
     *
     * @param float $latitude
     *
     * @return $this
     */
    public function setLatitude($latitude)
    {
        $this->latitude = (float) $latitude;

        return $this;
    }

    /**
     * Gets latitude.
     *
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Sets longitude.
     *
     * @param float $longitude
     *
     * @return $this
     */
    public function setLongitude($longitude)
    {
        $this->longitude = (float) $longitude;

        return $this;
    }

    /**
     * Gets longitude.
     *
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }
}
