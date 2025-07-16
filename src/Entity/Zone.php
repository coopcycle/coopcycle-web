<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Polygon\Polygon;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
    ],
    paginationClientEnabled: true,
    security: "is_granted('ROLE_ADMIN')"
)]
class Zone
{
    /**
     * @var int
     */
    protected $id;

    #[Assert\Type(type: 'string')]
    protected $name;

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
        // FIXME Use GeoJson\GeoJson class
        if (!isset($geoJSON['type']) || !isset($geoJSON['coordinates'])
        ||  $geoJSON['type'] !== 'Polygon'
        ||  !is_array($geoJSON['coordinates'])) {
            throw new \InvalidArgumentException('Invalid GeoJSON');
        }

        $this->polygon = json_encode($geoJSON);

        return $this;
    }

    public function containsAddress(Address $address)
    {
        $geojson = $this->getGeoJSON();

        $coordinates = array_map(function ($coordinate) {
            return [ $coordinate[1], $coordinate[0] ];
        }, $geojson['coordinates'][0]);


        $polygon = new Polygon($coordinates);
        $polygon->setPrecision(5);

        $coordinate = new Coordinate([ $address->getGeo()->getLatitude(), $address->getGeo()->getLongitude() ]);

        return $polygon->pointInPolygon($coordinate);
    }
}
