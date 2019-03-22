<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190322095303 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE store DROP CONSTRAINT fk_ff575877f5b7af75');
        $this->addSql('DROP INDEX uniq_ff575877f5b7af75');
        $this->addSql('ALTER TABLE store RENAME COLUMN address_id TO default_address_id');
        $this->addSql('ALTER TABLE store ADD CONSTRAINT FK_FF575877BD94FB16 FOREIGN KEY (default_address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FF575877BD94FB16 ON store (default_address_id)');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE store DROP CONSTRAINT FK_FF575877BD94FB16');
        $this->addSql('DROP INDEX UNIQ_FF575877BD94FB16');
        $this->addSql('ALTER TABLE store RENAME COLUMN default_address_id TO address_id');
        $this->addSql('ALTER TABLE store ADD CONSTRAINT fk_ff575877f5b7af75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_ff575877f5b7af75 ON store (address_id)');
    }
}
