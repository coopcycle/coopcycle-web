<?php

namespace AppBundle\Entity\Base;

use ApiPlatform\Core\Annotation\ApiProperty;
use Geocoder\Model\Coordinates as GeocoderCoordinates;
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
     * @Groups({"address", "address_create", "task_create", "task_edit", "order_update", "cart", "delivery_create"})
     * @Assert\Type(type="float")
     */
    private $latitude;

    /**
     * @var float The longitude of a location. For example `-122.08585`.
     *
     * @Groups({"address", "address_create", "task_create", "task_edit", "order_update", "cart", "delivery_create"})
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

    public function isEqualTo(GeoCoordinates $other)
    {
        return $this->getLatitude() === $other->getLatitude() && $this->getLongitude() === $other->getLongitude();
    }

    public function toGeocoderCoordinates(): GeocoderCoordinates
    {
        return new GeocoderCoordinates($this->getLatitude(), $this->getLongitude());
    }
}
