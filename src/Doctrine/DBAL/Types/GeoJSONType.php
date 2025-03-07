<?php

namespace AppBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Jsor\Doctrine\PostGIS\Types\GeographyType;

class GeoJSONType extends GeographyType
{
    public function getName(): string
    {
        return 'geojson';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }

    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform): string
    {
        // We use ST_Force_2D, because GeoJSON may contain z-dimension
        return sprintf('ST_GeographyFromText(ST_AsText(ST_Force2D(ST_GeomFromGeoJSON(%s))))', $sqlExpr);
    }

    public function convertToPHPValueSQL($sqlExpr, $platform): string
    {
        // ::geometry type cast needed for 1.5
        return sprintf('ST_AsGeoJSON(%s::geometry)', $sqlExpr);
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        $options = $this->getNormalizedPostGISColumnOptions($fieldDeclaration);

        return sprintf(
            '%s(%s, %d)',
            'geography',
            $options['geometry_type'],
            $options['srid']
        );
    }
}
