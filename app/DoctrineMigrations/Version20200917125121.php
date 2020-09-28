<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200917125121 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_variant ADD enabled BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_taxon ADD enabled BOOLEAN DEFAULT NULL');

        $this->addSql('UPDATE sylius_product_variant SET enabled = \'t\'');
        $this->addSql('UPDATE sylius_taxon SET enabled = \'t\'');

        $this->addSql('ALTER TABLE sylius_product_variant ALTER enabled SET NOT NULL');
        $this->addSql('ALTER TABLE sylius_taxon ALTER enabled SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_variant DROP enabled');
        $this->addSql('ALTER TABLE sylius_taxon DROP enabled');
    }
}
