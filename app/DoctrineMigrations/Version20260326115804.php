<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326115804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_product ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_option ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_option_value ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_taxon ADD metadata JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_product_option DROP metadata');
        $this->addSql('ALTER TABLE sylius_product_option_value DROP metadata');
        $this->addSql('ALTER TABLE sylius_product DROP metadata');
        $this->addSql('ALTER TABLE sylius_taxon DROP metadata');
    }
}
