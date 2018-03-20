<?php

namespace AppBundle\Entity\Base;

use Symfony\Component\Serializer\Annotation\Groups;
use AppBundle\Utils\GeoUtils;

/**
 * Entities that have a somewhat fixed, physical extension.
 *
 * @see http://schema.org/Place Documentation on Schema.org
 */
abstract class Place extends PostalAddress
{
    /**
     * @var GeoCoordinates The geo coordinates of the place.
     *
     * @Groups({"place"})
     */
    protected $geo;

    /**
     * Sets geo.
     *
     * @param GeoCoordinates $geo
     *
     * @return $this
     */
    public function setGeo(GeoCoordinates $coords)
    {
        $value = GeoUtils::asPoint($coords);

        $this->geo = $value;

        return $this;
    }

    /**
     * Gets geo.
     *
     * @return GeoCoordinates
     */
    public function getGeo()
    {
        if (null !== $this->geo) {
            return GeoUtils::asGeoCoordinates($this->geo);
        }
    }
}
