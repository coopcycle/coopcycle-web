<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220217134929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE store_time_slot (store_id INT NOT NULL, time_slot_id INT NOT NULL, PRIMARY KEY(store_id, time_slot_id))');
        $this->addSql('CREATE INDEX IDX_5CE54537B092A811 ON store_time_slot (store_id)');
        $this->addSql('CREATE INDEX IDX_5CE54537D62B0FA ON store_time_slot (time_slot_id)');
        $this->addSql('ALTER TABLE store_time_slot ADD CONSTRAINT FK_5CE54537B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store_time_slot ADD CONSTRAINT FK_5CE54537D62B0FA FOREIGN KEY (time_slot_id) REFERENCES time_slot (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO store_time_slot (store_id, time_slot_id) SELECT id, time_slot_id FROM store WHERE time_slot_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE store_time_slot');
    }
}
