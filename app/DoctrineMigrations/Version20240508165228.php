<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240508165228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Data migration from task_collection -> task_list and task_collection_item -> task_list_item';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        // copy data from task_collection
        $this->addSql('update task_list set
            created_at = tc.created_at,
            updated_at = tc.updated_at,
            polyline = tc.polyline,
            duration = tc.duration,
            distance = tc.distance
            from task_collection tc
            where task_list.id = tc.id;'
        );

        // tasks belonging to tours, create TaskListItem linked to tours
        // select on task_collection_item tci the tour ID - it is linked to the task_collection with type tour
        // select on task_collection_item tci_tl the tasklist ID - it is linked to the task_collection with type task_list
        // take the MIN of matching task_collection_item tci_tl to set tour position at the "beginning" of tour
        $this->addSql(
            "INSERT INTO task_list_item (parent_id, tour_id, position) (
                select
                    tci_tl.parent_id as parent_id,
                    tc.id  as tour_id,
                    min(tci_tl.position) as position
                from task_collection_item tci
                inner join task_collection tc on (tc.id = tci.parent_id and tc.type ='tour')
                inner join task_collection_item tci_tl on tci_tl.id in (
                    select task_collection_item.id from task_collection_item inner join task_collection on task_collection.id = task_collection_item.parent_id  where task_collection.type = 'task_list'
                ) and tci_tl.task_id = tci.task_id
                group by tci_tl.parent_id, tc.id
            );"
        );

        // standalone tasks create TaskListItem linked to tasks
        $this->addSql(
            "INSERT INTO task_list_item (parent_id, position, task_id) (
                select parent_id, position, task_id
                from task_collection_item tci
                inner join task_collection tc on tci.parent_id = tc.id
                inner join task t on tci.task_id = t.id
                where tc.type = 'task_list' and t.tour_id is null
            );"
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
