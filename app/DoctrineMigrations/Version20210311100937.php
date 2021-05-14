<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210311100937 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_adjustment ADD details JSON DEFAULT NULL');
        $this->addSql('UPDATE sylius_adjustment SET details = \'{}\'');
        $this->addSql('ALTER TABLE sylius_adjustment ALTER details SET NOT NULL');

        $this->addSql('ALTER TABLE sylius_product_attribute ADD translatable BOOLEAN DEFAULT \'true\' NOT NULL');
        $this->addSql('ALTER TABLE sylius_product_attribute_value ALTER locale_code DROP NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_attribute DROP translatable');
        $this->addSql('ALTER TABLE sylius_adjustment DROP details');
        $this->addSql('ALTER TABLE sylius_product_attribute_value ALTER locale_code SET NOT NULL');
    }
}
