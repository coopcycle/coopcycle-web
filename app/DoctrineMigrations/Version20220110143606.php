<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220110143606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_order ADD nonprofit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F9E24E94E2 FOREIGN KEY (nonprofit_id) REFERENCES nonprofit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6196A1F9E24E94E2 ON sylius_order (nonprofit_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT FK_6196A1F9E24E94E2');
        $this->addSql('DROP INDEX IDX_6196A1F9E24E94E2');
        $this->addSql('ALTER TABLE sylius_order DROP nonprofit_id');
    }
}
