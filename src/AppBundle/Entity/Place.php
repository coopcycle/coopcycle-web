<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Entities that have a somewhat fixed, physical extension.
 *
 * @see http://schema.org/Place Documentation on Schema.org
 *
 * @ORM\MappedSuperclass
 */
abstract class Place extends PostalAddress
{
    /**
     * @var GeoCoordinates The geo coordinates of the place.
     *
     * @Groups({"place"})
     * @ORM\Column(type="geography", nullable=true)
     * _@ApiProperty(iri="https://schema.org/geocoordinates")
     */
    private $geo;

    /**
     * Sets geo.
     *
     * @param GeoCoordinates $geo
     *
     * @return $this
     */
    public function setGeo($geo = null)
    {
        $this->geo = $geo;

        return $this;
    }

    /**
     * Gets geo.
     *
     * @return GeoCoordinates
     */
    public function getGeo()
    {
        // preg_match('/POINT\(([0-9\.]+) ([0-9\.]+)\)/', $this->geo, $matches);

        // $latitude = $matches[1];
        // $longitude = $matches[2];

        // $geo = new GeoCoordinates();
        // $geo->setLatitude($latitude);
        // $geo->setLongitude($longitude);

        // return $geo;

        return $this->geo;

        // return new \StdClass();
    }
}
