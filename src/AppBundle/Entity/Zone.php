<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(
 *   indexes={
 *     @ORM\Index(name="idx_polygon", columns={"polygon"}, flags={"spatial"})
 *   }
 * )
 */
class Zone
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
     * @Assert\Type(type="string")
     * @ORM\Column
     */
    protected $name;

    /**
     * @ORM\Column(type="geojson", options={"geometry_type"="POLYGON", "srid"=4326})
     */
    protected $polygon;

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getGeoJSON()
    {
        return json_decode($this->polygon, true);
    }

    public function setGeoJSON(array $geoJSON)
    {
        if (!isset($geoJSON['type']) || !isset($geoJSON['coordinates'])
        ||  $geoJSON['type'] !== 'Polygon'
        ||  !is_array($geoJSON['coordinates'])) {
            throw new \InvalidArgumentException('Invalid GeoJSON');
        }

        $this->polygon = json_encode($geoJSON);

        return $this;
    }
}
