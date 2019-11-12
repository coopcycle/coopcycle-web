<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180226131303 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10BF396750 FOREIGN KEY (id) REFERENCES task_collection (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE delivery_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10BF396750');
        $this->addSql('CREATE SEQUENCE delivery_id_seq');
        $this->addSql('SELECT setval(\'delivery_id_seq\', (SELECT MAX(id) FROM delivery))');
        $this->addSql('ALTER TABLE delivery ALTER id SET DEFAULT nextval(\'delivery_id_seq\')');
    }
}
