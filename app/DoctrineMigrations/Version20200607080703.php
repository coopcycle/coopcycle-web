<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200607080703 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('CREATE TABLE sylius_tax_category_country (id SERIAL NOT NULL, category_id INT DEFAULT NULL, country VARCHAR(6) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_46EFBCA112469DE2 ON sylius_tax_category_country (category_id)');
        $this->addSql('ALTER TABLE sylius_tax_category_country ADD CONSTRAINT FK_46EFBCA112469DE2 FOREIGN KEY (category_id) REFERENCES sylius_tax_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_tax_rate ADD country VARCHAR(6) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DROP TABLE sylius_tax_category_country');
        $this->addSql('ALTER TABLE sylius_tax_rate DROP country');
    }
}
