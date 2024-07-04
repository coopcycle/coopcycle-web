<?php

namespace AppBundle\Doctrine\PostGIS;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Jsor\Doctrine\PostGIS\Event\ORMSchemaEventSubscriber as BaseORMSchemaEventSubscriber;
use Jsor\Doctrine\PostGIS\Types\PostGISType;

class ORMSchemaEventSubscriber extends BaseORMSchemaEventSubscriber
{
    public function onSchemaColumnDefinition(SchemaColumnDefinitionEventArgs $args): void
    {
        parent::onSchemaColumnDefinition($args);

        $column = $args->getColumn();

        if (!$column) {
            return;
        }

        if (!$column->getType() instanceof PostGISType) {
            return;
        }

        $comment = $args->getColumn()->getComment();

        if (!$comment) {
            return;
        }

        $geoJSONComment = $args->getConnection()->getDriver()->getDatabasePlatform()->getDoctrineTypeComment(Type::getType('geojson'));

        if ($comment === $geoJSONComment) {
            $args->getColumn()->setType(Type::getType('geojson'));
        }
    }
}
