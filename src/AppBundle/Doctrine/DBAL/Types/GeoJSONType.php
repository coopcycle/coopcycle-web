<?php

namespace AppBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Jsor\Doctrine\PostGIS\Types\GeographyType;

class GeoJSONType extends GeographyType
{
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return sprintf('ST_GeographyFromText(ST_AsText(ST_GeomFromGeoJSON(%s)))', $sqlExpr);
    }

    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        // ::geometry type cast needed for 1.5
        return sprintf('ST_AsGeoJSON(%s::geometry)', $sqlExpr);
    }
}
