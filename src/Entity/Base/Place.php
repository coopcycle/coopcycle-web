<?php

namespace AppBundle\Entity\Base;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
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
     * @Assert\NotNull()
     * @Groups({"address", "address_create", "task_create", "task_edit", "order_update", "cart", "delivery_create"})
     */
    protected $geo;

    /**
     * Sets geo.
     *
     * @param GeoCoordinates $coords
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
     * @return GeoCoordinates|null
     */
    public function getGeo()
    {
        if (null !== $this->geo) {
            return GeoUtils::asGeoCoordinates($this->geo);
        }

        return null;
    }
}
