<?php

namespace AppBundle\Doctrine\PostGIS;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Jsor\Doctrine\PostGIS\Event\ORMSchemaEventSubscriber as BaseORMSchemaEventSubscriber;

class ORMSchemaEventSubscriber extends BaseORMSchemaEventSubscriber
{
    public function onSchemaColumnDefinition(SchemaColumnDefinitionEventArgs $args)
    {
        parent::onSchemaColumnDefinition($args);

        $column = $args->getColumn();

        if (!$column) {
            return;
        }

        if (!$this->isSpatialColumnType($column)) {
            return;
        }

        $comment = $args->getColumn()->getComment();

        if (!$comment) {
            return;
        }

        $geoJSONComment = $args->getDatabasePlatform()->getDoctrineTypeComment(Type::getType('geojson'));

        if ($comment === $geoJSONComment) {
            $args->getColumn()->setType(Type::getType('geojson'));
        }
    }
}
