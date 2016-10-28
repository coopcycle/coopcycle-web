<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * The geographic coordinates of a place or event.
 *
 * @see http://schema.org/GeoCoordinates Documentation on Schema.org
 * _@ApiProperty(iri="http://schema.org/GeoCoordinates")
 */
class GeoCoordinates
{
    /**
     * @var int
     *
     * _@ORM\Column(type="integer")
     * _@ORM\Id
     * _@ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var float The latitude of a location. For example `37.42242`.
     *
     * @Groups({"customer"})
     * @Assert\Type(type="float")
     * _@ApiProperty(iri="https://schema.org/latitude")
     */
    private $latitude;

    /**
     * @var float The longitude of a location. For example `-122.08585`.
     *
     * @Groups({"customer"})
     * @Assert\Type(type="float")
     * _@ApiProperty(iri="https://schema.org/longitude")
     */
    private $longitude;

    /**
     * Sets id.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
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
        $this->latitude = $latitude;

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
        $this->longitude = $longitude;

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
