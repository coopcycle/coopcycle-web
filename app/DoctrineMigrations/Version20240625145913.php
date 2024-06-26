<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240625145913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store_time_slot ALTER time_slot_id DROP NOT NULL');
        $this->addSql('ALTER TABLE store_time_slot ALTER store_id DROP NOT NULL');
        $this->addSql('ALTER TABLE store_time_slot ALTER "position" DROP DEFAULT');
        $this->addSql('ALTER TABLE store_time_slot ADD CONSTRAINT FK_5CE54537B092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5CE54537B092A811D62B0FA ON store_time_slot (store_id, time_slot_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store_time_slot DROP CONSTRAINT FK_5CE54537B092A811');
        $this->addSql('DROP INDEX UNIQ_5CE54537B092A811D62B0FA');
        $this->addSql('ALTER TABLE store_time_slot ALTER store_id SET NOT NULL');
        $this->addSql('ALTER TABLE store_time_slot ALTER time_slot_id SET NOT NULL');
        $this->addSql('ALTER TABLE store_time_slot ALTER position SET DEFAULT 0');
    }
}
