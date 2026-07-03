<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260317111322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_product_option_value_depends_on (source_product_option_value_id INT NOT NULL, target_product_option_value_id INT NOT NULL, PRIMARY KEY(source_product_option_value_id, target_product_option_value_id))');
        $this->addSql('CREATE INDEX IDX_799169C924D34B74 ON sylius_product_option_value_depends_on (source_product_option_value_id)');
        $this->addSql('CREATE INDEX IDX_799169C939BEB5A4 ON sylius_product_option_value_depends_on (target_product_option_value_id)');
        $this->addSql('ALTER TABLE sylius_product_option_value_depends_on ADD CONSTRAINT FK_799169C924D34B74 FOREIGN KEY (source_product_option_value_id) REFERENCES sylius_product_option_value (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_option_value_depends_on ADD CONSTRAINT FK_799169C939BEB5A4 FOREIGN KEY (target_product_option_value_id) REFERENCES sylius_product_option_value (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_option_value_depends_on DROP CONSTRAINT FK_799169C924D34B74');
        $this->addSql('ALTER TABLE sylius_product_option_value_depends_on DROP CONSTRAINT FK_799169C939BEB5A4');
        $this->addSql('DROP TABLE sylius_product_option_value_depends_on');
    }
}
