<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711072931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add schedule_publication and shift_waitlist_entry tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE schedule_publication (id SERIAL NOT NULL, published_by_id INT DEFAULT NULL, week_start DATE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_37BE8DB25B075477 ON schedule_publication (published_by_id)');
        $this->addSql('CREATE UNIQUE INDEX schedule_publication_week_unique ON schedule_publication (week_start)');
        $this->addSql('CREATE TABLE shift_waitlist_entry (id SERIAL NOT NULL, shift_id INT NOT NULL, user_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A133EEFFBB70BC0E ON shift_waitlist_entry (shift_id)');
        $this->addSql('CREATE INDEX IDX_A133EEFFA76ED395 ON shift_waitlist_entry (user_id)');
        $this->addSql('CREATE UNIQUE INDEX shift_waitlist_unique ON shift_waitlist_entry (shift_id, user_id)');
        $this->addSql('ALTER TABLE schedule_publication ADD CONSTRAINT FK_37BE8DB25B075477 FOREIGN KEY (published_by_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_waitlist_entry ADD CONSTRAINT FK_A133EEFFBB70BC0E FOREIGN KEY (shift_id) REFERENCES shift (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_waitlist_entry ADD CONSTRAINT FK_A133EEFFA76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedule_publication DROP CONSTRAINT FK_37BE8DB25B075477');
        $this->addSql('ALTER TABLE shift_waitlist_entry DROP CONSTRAINT FK_A133EEFFBB70BC0E');
        $this->addSql('ALTER TABLE shift_waitlist_entry DROP CONSTRAINT FK_A133EEFFA76ED395');
        $this->addSql('DROP TABLE schedule_publication');
        $this->addSql('DROP TABLE shift_waitlist_entry');
    }
}
