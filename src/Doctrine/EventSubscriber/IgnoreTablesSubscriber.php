<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Sylius\OrderView;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * @see http://kamiladryjanek.com/ignore-entity-or-table-when-running-doctrine2-schema-update-command/
 */
class IgnoreTablesSubscriber implements EventSubscriber
{
    private $ignoredTables = [];

    private $ignoredEntities = [
        OrderView::class,
    ];

    public function getSubscribedEvents()
    {
        return array(
            ToolEvents::postGenerateSchema,
        );
    }

    /**
     * Remove ignored tables /entities from Schema
     *
     * @param GenerateSchemaEventArgs $args
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args)
    {
        $schema = $args->getSchema();
        $em = $args->getEntityManager();

        $ignoredTables = $this->ignoredTables;

        foreach ($this->ignoredEntities as $entityName) {
            $ignoredTables[] = $em->getClassMetadata($entityName)->getTableName();
        }

        foreach ($schema->getTables() as $table) {
            if (in_array($table->getName(), $ignoredTables)) {
                // Remove table from schema
                $schema->dropTable($table->getName());
            }

        }
    }
}
