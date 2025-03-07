<?php

namespace AppBundle\Entity;

use Gedmo\Timestampable\Traits\Timestampable;
use GeoJson\Geometry\Polygon;

class CityZone
{
    use Timestampable;

    private $id;
    private $name;
    private $polygon;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    public function getGeoJSON()
    {
        return json_decode($this->polygon, true);
    }

    public function setGeoJSON(array|Polygon $geoJSON)
    {
    	if (!$geoJSON instanceof Polygon) {
	        if (!isset($geoJSON['type']) || !isset($geoJSON['coordinates'])
	        ||  $geoJSON['type'] !== 'Polygon'
	        ||  !is_array($geoJSON['coordinates'])) {
	            throw new \InvalidArgumentException('Invalid GeoJSON');
	        }
    	}

        $this->polygon = json_encode($geoJSON);

        return $this;
    }
}
