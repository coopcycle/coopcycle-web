<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Utils\GeoUtils;

class TrackingPosition implements \JsonSerializable
{
    /**
     * @var int
     */
    protected $id;

    private $courier;

    protected $coordinates;

    protected $date;

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getCourier()
    {
        return $this->courier;
    }

    public function setCourier(User $courier)
    {
        $this->courier = $courier;

        return $this;
    }

    public function setCoordinates(GeoCoordinates $coords)
    {
        $value = GeoUtils::asPoint($coords);

        $this->coordinates = $value;

        return $this;
    }

    public function getCoordinates()
    {
        if (null !== $this->coordinates) {
            return GeoUtils::asGeoCoordinates($this->coordinates);
        }
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'coordinates' => [
                'latitude' => $this->getCoordinates()->getLatitude(),
                'longitude' => $this->getCoordinates()->getLongitude(),
            ]
        ];
    }
}
