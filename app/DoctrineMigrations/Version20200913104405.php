<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200913104405 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product DROP CONSTRAINT FK_677B9B74B26ADE57');
        $this->addSql('ALTER TABLE sylius_product ADD CONSTRAINT FK_677B9B74B26ADE57 FOREIGN KEY (reusable_packaging_id) REFERENCES reusable_packaging (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product DROP CONSTRAINT fk_677b9b74b26ade57');
        $this->addSql('ALTER TABLE sylius_product ADD CONSTRAINT fk_677b9b74b26ade57 FOREIGN KEY (reusable_packaging_id) REFERENCES reusable_packaging (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
