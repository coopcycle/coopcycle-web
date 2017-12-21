<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Utils\GeoUtils;

/**
 * @ORM\Entity
 * @ORM\Table(
 *   indexes={
 *     @ORM\Index(name="idx_tracking_position_coordinates", columns={"coordinates"}, flags={"spatial"})
 *   }
 * )
 */
class TrackingPosition implements \JsonSerializable
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="ApiUser")
     */
    private $courier;

    /**
     * @ORM\Column(type="geography", options={"geometry_type"="GEOMETRY", "srid"=4326})
     */
    protected $coordinates;

    /**
     * @ORM\Column(type="datetime")
     */
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

    public function setCourier(ApiUser $courier)
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

    public function jsonSerialize()
    {
        return [
            'coordinates' => [
                'latitude' => $this->getCoordinates()->getLatitude(),
                'longitude' => $this->getCoordinates()->getLongitude(),
            ]
        ];
    }
}
