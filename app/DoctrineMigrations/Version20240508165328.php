<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240508165328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop link between task_list and task_collection';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_list DROP CONSTRAINT fk_377b6c63bf396750'); // drop link with task_collection
        $this->addSql('CREATE SEQUENCE task_list_id_seq');
        $this->addSql('SELECT setval(\'task_list_id_seq\', (SELECT MAX(id) FROM task_list))');
        $this->addSql('ALTER TABLE task_list ALTER id SET DEFAULT nextval(\'task_list_id_seq\')');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_list ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE task_list ADD CONSTRAINT fk_377b6c63bf396750 FOREIGN KEY (id) REFERENCES task_collection (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
