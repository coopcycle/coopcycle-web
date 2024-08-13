<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240813130833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE package DROP CONSTRAINT FK_DE6867952E007EC4');
        $this->addSql('ALTER TABLE package ADD CONSTRAINT FK_DE6867952E007EC4 FOREIGN KEY (package_set_id) REFERENCES package_set (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE package DROP CONSTRAINT fk_de6867952e007ec4');
        $this->addSql('ALTER TABLE package ADD CONSTRAINT fk_de6867952e007ec4 FOREIGN KEY (package_set_id) REFERENCES package_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
